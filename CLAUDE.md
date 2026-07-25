# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this project is

Pluma is a from-scratch Laravel rebuild of [Postiz](https://github.com/gitroomhq/postiz-app), a social-media scheduling SaaS. The original is NestJS + Prisma/Postgres + Temporal (workflow engine) + Redis + Next.js/React; this rewrite replaces that entire stack with Laravel + Blade/Livewire.

This is a rewrite, not a port of Postiz's code — Postiz is AGPL-3.0, and this app is intended to be resold as closed-source multi-tenant SaaS, which AGPL's network-use clause would not allow for a fork. Only architectural ideas are being carried over, not literal code.

The full conversion plan (backend architecture, integrations-layer design, frontend plan, phased build order) lives at `/Users/ebenezerdaniel/.claude/plans/can-you-convert-this-warm-pnueli.md`. Read it before starting a new phase of work — it defines what belongs in which phase and why.

Priority social integrations to build (before expanding to Postiz's other ~30): Instagram, Facebook, LinkedIn, YouTube, X (Twitter), WhatsApp, Snapchat. WhatsApp and Snapchat aren't in original Postiz and have real product constraints, not just implementation gaps: Snapchat has no public API for organic/personal post scheduling (only the Marketing API for paid ads), and WhatsApp Business Cloud API is targeted broadcast messaging to opted-in contacts, not public feed posts — structurally different from the other 6 platforms.

## Commands

All PHP/artisan commands run **inside Docker via Sail** — do not install Postgres/Redis on the host. The app runs at `http://localhost:8090` (Postgres on 5433, Redis on 6380, Mailpit UI on 8026 — shifted off Sail's defaults to avoid clashing with other local Sail projects on this machine).

```bash
# Start/stop the stack
./vendor/bin/sail up -d
./vendor/bin/sail down

# Run the full test suite
./vendor/bin/sail artisan test

# Run a single test file / method
./vendor/bin/sail artisan test tests/Feature/PostCrudTest.php
./vendor/bin/sail artisan test --filter=test_a_post_can_be_created_and_is_scoped_to_the_current_organization

# Migrations (prefer this over migrate:fresh once a test account exists —
# migrate:fresh wipes all local data including logins)
./vendor/bin/sail artisan migrate

# Composer / npm (also run through Sail so they use the container's PHP/Node)
./vendor/bin/sail composer require <package>
./vendor/bin/sail npm run build
./vendor/bin/sail npm run dev

# Tinker
./vendor/bin/sail artisan tinker
```

No CI/lint command is configured yet (`laravel/pint` is installed as a dev dependency but has no project-specific config or script wired up).

## Architecture

### Domain-oriented structure, not the default Laravel layout

Business logic lives under `app/Domain/{Organization,Auth,Billing,Posts,Integrations}/{Models,Actions,Policies,Enums,Concerns,Support,Contracts,Providers}`, not `app/Models`/`app/Http/Controllers`. Controllers and Livewire components stay thin and call single-purpose Action classes (e.g. `App\Domain\Posts\Actions\CreatePost`). `app/Models/User.php` and Fortify's actions under `app/Actions/Fortify` are the only things left in Laravel's default locations, since Jetstream/Fortify scaffolding expects them there.

When adding a new domain, follow this same folder shape rather than flattening things into `app/Models`.

### Organization is Jetstream's Team, renamed and extended

Jetstream ships a `Team` concept (teams/team_user/team_invitations tables, a `HasTeams` trait on `User`). This app renames that to `Organization` (`App\Domain\Organization\Models\Organization`, tables `organizations`/`organization_user`/`organization_invitations`) to match Postiz's domain language, and it's also the Cashier `Billable` model (billing is per-org, not per-user) and the media library owner (see below).

**Important gotcha:** Jetstream's `HasTeams` trait and base `Team` class hardcode a handful of literal column names across several methods — `user_id` (owner FK), `personal_team`, and `current_team_id` (on `users`). These were deliberately *not* renamed even though everything else was, because doing so would mean overriding those vendor methods. If you see `current_team_id` or `personal_team` in code, that's why — it's not an oversight.

Relationship/method names inherited from Jetstream (`$user->currentTeam`, `$user->ownedTeams()`, `$organization->users()`, `$organization->teamInvitations()`) are also kept as-is rather than renamed to `currentOrganization()` etc., since Jetstream's own controllers and Blade component internals call them by these names. Only the **model class** and **user-facing Blade copy** say "Organization" — internal plumbing still says "Team" in places.

Roles are custom: `superadmin`/`admin`/`user` (defined in `JetstreamServiceProvider::configurePermissions()`), replacing Jetstream's default demo `admin`/`editor` roles. The org creator is implicitly full-access via Jetstream's "owner" concept regardless of their pivot role.

### Multi-tenancy: global scope, not a tenancy package

`App\Domain\Organization\Concerns\BelongsToOrganization` is the safety net — any model that uses it gets a global scope filtering every query to the active organization, and auto-fills `organization_id` on create. "Active organization" is resolved by `App\Domain\Organization\Support\CurrentOrganization`, which defaults to `Auth::user()->currentTeam` for normal web requests.

The scope is a no-op when no organization is resolvable (rather than hiding all rows) — this is intentional so console commands, tests, and future cross-tenant admin tooling can still query freely by being explicit. Background jobs have no authenticated user, so they must call `CurrentOrganization::set($organization)` before touching any scoped model and `clear()` afterward — see `PublishPostJob` for the pattern.

`Post` and `Tag` use this trait; `PostComment` and `PostError` deliberately don't (they inherit tenant isolation transitively through their parent `Post`).

### Media library is organization-level, not per-post

`Organization` implements `HasMedia`/`InteractsWithMedia` (spatie/laravel-medialibrary) with a `'library'` collection — this matches Postiz's model where uploaded assets are a shared org-wide library, not owned by individual posts. `Post` also implements `InteractsWithMedia` separately for per-post attachments once the composer UI exists. There's no conversions/thumbnails pipeline configured yet — the media UI shows original files directly.

### Publishing pipeline: Laravel queue + scheduler replaces Temporal

`posts:dispatch-due` (`app/Console/Commands/DispatchDuePosts.php`, scheduled every minute in `routes/console.php`) queries every organization for posts in the `Queue` state with a due `scheduled_at` **and a non-null `integration_id`**, bypassing the tenancy scope (`Post::withoutGlobalScope('organization')`) since it runs with no authenticated user. This single poller covers both Postiz's "autopost" and "missing post recovery" Temporal workflows — a post that was somehow skipped just gets picked up on the next run, no separate recovery logic needed.

`PublishPostJob` (`app/Domain/Posts/Jobs`) takes only a post ID, not the model — it re-fetches fresh state at execution time so in-flight jobs survive deploys/queue restarts rather than carrying a stale serialized snapshot. 5 retries, backoff `30,120,600,1800,3600` seconds, `WithoutOverlapping` keyed by post ID to guard against the poller double-dispatching for a still-running job. Each failed attempt records a typed `PostError` (`token_expired` for `RefreshTokenException`, `platform_error` for `BadBodyException`); `failed()` (retries exhausted) additionally marks the post `Error`.

It resolves the actual provider via `SocialProviderManager::driver($post->integration->provider)` — see the Integrations section below. A post with no `integration_id` is a Phase 2-style plain draft, not eligible for dispatch at all.

### Integrations layer (`app/Domain/Integrations`)

One class per platform implementing `Contracts\SocialProviderContract` (`key()`, `label()`, `socialiteDriver()`, `scopes()`, `connect()`, `refreshToken()`, `checkValidity()`, `post()`), registered in `config/social-providers.php` — adding platform #4 is "one class + one config line" via `SocialProviderManager`. `AbstractSocialProvider` implements the shared `connect()` (find-or-create the `Integration` row from a Socialite user) and a `request()`/`assertSuccessful()` helper that maps failed HTTP responses to `RefreshTokenException` (401) or `BadBodyException` (anything else failed).

Real providers lean on **Socialite's own OAuth dance** rather than reimplementing it — `IntegrationConnectController` (`app/Http/Controllers`) is a generic `redirect()`/`callback()` pair driving whichever provider's `socialiteDriver()`/`scopes()` say to use. `LinkedInProvider` uses the `linkedin-openid` driver plus the `w_member_social` scope (personal-profile posting only — org-page posting needs LinkedIn's separate Marketing Developer Platform partner approval) and posts via LinkedIn's `/rest/posts` API. `XProvider` uses Socialite's built-in `x` driver (OAuth2 + PKCE, already includes refresh-token support) plus `tweet.write offline.access` scopes, posting via `/2/tweets`. Both were verified for real: connecting redirects all the way to the platform's live OAuth authorize endpoint with correctly merged scopes — only real `client_id`/`client_secret` env vars are missing until you register developer apps on each platform.

`FakeProvider` (registry key `fake`) is Phase 3's `FakeSocialPublisher` generalized into the same registry — useful for local dev/tests without real API keys, deliberately excluded from the connect UI and from `IntegrationConnectController`'s allow-list (only real providers are connectable).

`Integration` (org-scoped via `BelongsToOrganization`) is distinct from `App\Domain\Auth\Models\SocialAccount` (Google/GitHub *login* linkage) — don't conflate the two. Access/refresh tokens are stored via Laravel's `encrypted` cast, not custom encryption code.

### Launches: the calendar + composer (`app/Livewire/Launches`)

The first fully real user-facing feature. `Calendar` renders every post that has both an `integration_id` and a `scheduled_at` as a FullCalendar event (`resources/js/app.js`'s `launchesCalendar` Alpine component, `wire:ignore`-wrapped so Livewire's morphing never touches FullCalendar's self-managed DOM). Dragging an event calls `$wire.reschedule()`, which reverts the drag client-side if the post isn't still Draft/Queue server-side. Clicking a date opens the composer prefilled via a `#[Url]`-bound `date` query param; clicking an event opens it in edit mode.

`Composer` embeds TipTap (`postComposer` Alpine component, also `wire:ignore`-wrapped) syncing plain text to a Livewire property via `onUpdate` → `$wire.set('content', ...)` — content is plain text, not HTML, since X/LinkedIn both post plain text. Selecting an integration surfaces that platform's character limit (a plain lookup table, `Composer::CHARACTER_LIMITS`) rather than per-editor config, since there's no rich-content mapping to a specific platform's formatting to worry about yet.

This composer (integration-aware, required target) coexists with Phase 2's plain `/posts` CRUD (no integration, freeform state) rather than replacing it — the latter is still there for drafts not yet tied to a publishing target.

### Auth

Fortify (registration/login/password reset/2FA) + Jetstream (Livewire stack, teams) + Socialite (Google/GitHub). A single `REGISTRATION_DISABLED` env flag (read via `config/features.php`) gates both Fortify's registration feature and the Socialite auto-register path in `App\Domain\Auth\Actions\SocialLoginService` — setting it unregisters `/register` entirely (404), not just hides the link. `App\Domain\Auth\Models\SocialAccount` tracks Google/GitHub login linkage and is unrelated to the later `Integration` model for actual social-media *publishing* connections — don't conflate the two.

### Billing

Cashier's customer/subscription migrations were retargeted from `users` to `organizations`, including the subscriptions table's FK column (`organization_id`, not `user_id` — Cashier's `ManagesSubscriptions` relation derives the FK from the Billable model's class name via `getForeignKey()`, so this isn't cosmetic). `config/billing.php` maps Postiz's STANDARD/PRO/TEAM/ULTIMATE tiers to Stripe Price IDs. `App\Domain\Billing\Http\Controllers\StripeWebhookController` extends Cashier's own webhook controller to additionally sync `organizations.subscription_tier` from subscription events, so feature-gating can read a plain column instead of querying Stripe/Cashier tables per request. Cashier's own route registration is disabled (`Cashier::ignoreRoutes()` in `AppServiceProvider`) in favor of routes defined in `routes/web.php` pointing at this app's own controller, kept under the same route names (`cashier.webhook`, `cashier.payment`) Cashier normally uses.
