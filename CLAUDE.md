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

Business logic lives under `app/Domain/{Organization,Auth,Billing,Posts}/{Models,Actions,Policies,Enums,Concerns,Support}`, not `app/Models`/`app/Http/Controllers`. Controllers and Livewire components stay thin and call single-purpose Action classes (e.g. `App\Domain\Posts\Actions\CreatePost`). `app/Models/User.php` and Fortify's actions under `app/Actions/Fortify` are the only things left in Laravel's default locations, since Jetstream/Fortify scaffolding expects them there.

When adding a new domain (e.g. Integrations in a later phase), follow this same folder shape rather than flattening things into `app/Models`.

### Organization is Jetstream's Team, renamed and extended

Jetstream ships a `Team` concept (teams/team_user/team_invitations tables, a `HasTeams` trait on `User`). This app renames that to `Organization` (`App\Domain\Organization\Models\Organization`, tables `organizations`/`organization_user`/`organization_invitations`) to match Postiz's domain language, and it's also the Cashier `Billable` model (billing is per-org, not per-user) and the media library owner (see below).

**Important gotcha:** Jetstream's `HasTeams` trait and base `Team` class hardcode a handful of literal column names across several methods — `user_id` (owner FK), `personal_team`, and `current_team_id` (on `users`). These were deliberately *not* renamed even though everything else was, because doing so would mean overriding those vendor methods. If you see `current_team_id` or `personal_team` in code, that's why — it's not an oversight.

Relationship/method names inherited from Jetstream (`$user->currentTeam`, `$user->ownedTeams()`, `$organization->users()`, `$organization->teamInvitations()`) are also kept as-is rather than renamed to `currentOrganization()` etc., since Jetstream's own controllers and Blade component internals call them by these names. Only the **model class** and **user-facing Blade copy** say "Organization" — internal plumbing still says "Team" in places.

Roles are custom: `superadmin`/`admin`/`user` (defined in `JetstreamServiceProvider::configurePermissions()`), replacing Jetstream's default demo `admin`/`editor` roles. The org creator is implicitly full-access via Jetstream's "owner" concept regardless of their pivot role.

### Multi-tenancy: global scope, not a tenancy package

`App\Domain\Organization\Concerns\BelongsToOrganization` is the safety net — any model that uses it gets a global scope filtering every query to the active organization, and auto-fills `organization_id` on create. "Active organization" is resolved by `App\Domain\Organization\Support\CurrentOrganization`, which defaults to `Auth::user()->currentTeam` for normal web requests.

The scope is a no-op when no organization is resolvable (rather than hiding all rows) — this is intentional so console commands, tests, and future cross-tenant admin tooling can still query freely by being explicit. Background jobs (starting Phase 3, which has no authenticated user) must call `CurrentOrganization::set($organization)` before touching any scoped model and `clear()` afterward.

`Post` and `Tag` use this trait; `PostComment` and `PostError` deliberately don't (they inherit tenant isolation transitively through their parent `Post`).

### Media library is organization-level, not per-post

`Organization` implements `HasMedia`/`InteractsWithMedia` (spatie/laravel-medialibrary) with a `'library'` collection — this matches Postiz's model where uploaded assets are a shared org-wide library, not owned by individual posts. `Post` also implements `InteractsWithMedia` separately for per-post attachments once the composer UI exists. There's no conversions/thumbnails pipeline configured yet — the media UI shows original files directly.

### Post has no publishing pipeline yet

`Post`'s `state` enum (`App\Domain\Posts\Enums\PostState`: Draft/Queue/Published/Error) and `scheduled_at`/`published_at` columns exist, but nothing acts on them yet — a post just sits in whatever state it's given. The queue/scheduling engine (a `posts:dispatch-due` command + `PublishPostJob`, replacing Postiz's Temporal workflows with Laravel's queue + scheduler + Horizon) is the next phase per the plan doc. `PostError.integration_id` has no FK constraint yet because the `Integration` model (social-platform connections) doesn't exist until the integrations phase.

### Auth

Fortify (registration/login/password reset/2FA) + Jetstream (Livewire stack, teams) + Socialite (Google/GitHub). A single `REGISTRATION_DISABLED` env flag (read via `config/features.php`) gates both Fortify's registration feature and the Socialite auto-register path in `App\Domain\Auth\Actions\SocialLoginService` — setting it unregisters `/register` entirely (404), not just hides the link. `App\Domain\Auth\Models\SocialAccount` tracks Google/GitHub login linkage and is unrelated to the later `Integration` model for actual social-media *publishing* connections — don't conflate the two.

### Billing

Cashier's customer/subscription migrations were retargeted from `users` to `organizations`, including the subscriptions table's FK column (`organization_id`, not `user_id` — Cashier's `ManagesSubscriptions` relation derives the FK from the Billable model's class name via `getForeignKey()`, so this isn't cosmetic). `config/billing.php` maps Postiz's STANDARD/PRO/TEAM/ULTIMATE tiers to Stripe Price IDs. `App\Domain\Billing\Http\Controllers\StripeWebhookController` extends Cashier's own webhook controller to additionally sync `organizations.subscription_tier` from subscription events, so feature-gating can read a plain column instead of querying Stripe/Cashier tables per request. Cashier's own route registration is disabled (`Cashier::ignoreRoutes()` in `AppServiceProvider`) in favor of routes defined in `routes/web.php` pointing at this app's own controller, kept under the same route names (`cashier.webhook`, `cashier.payment`) Cashier normally uses.
