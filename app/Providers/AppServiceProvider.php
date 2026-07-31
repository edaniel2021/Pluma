<?php

namespace App\Providers;

use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Passport\Passport;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\GoogleProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Billing is per-organization (not per-user), matching Postiz.
        Cashier::useCustomerModel(Organization::class);

        // We register our own webhook route (routes/web.php) pointing at
        // StripeWebhookController so subscription events can also sync
        // organizations.subscription_tier - see that controller for why.
        Cashier::ignoreRoutes();

        // A distinct driver name (not just 'google') so YouTube's connect
        // flow gets its own redirect URI / config entry instead of
        // colliding with SocialAuthController's Google *login* flow, which
        // also uses Socialite's 'google' driver.
        Socialite::extend('youtube', function ($app) {
            return Socialite::buildProvider(GoogleProvider::class, config('services.youtube'));
        });

        // Same reasoning: Facebook and Instagram both authenticate through
        // the same Meta app/Facebook Login, but need their own redirect
        // URIs since they're separate Integration connect routes.
        Socialite::extend('instagram-facebook', function ($app) {
            return Socialite::buildProvider(FacebookProvider::class, config('services.instagram-facebook'));
        });

        // Tiered by the requesting token owner's organization subscription
        // tier (config/billing.php's api_rate_limits) - covers both a
        // first-party Sanctum personal access token and a third-party
        // Passport-issued OAuth token, since both resolve to a User via
        // $request->user().
        RateLimiter::for('api', function (Request $request) {
            $tier = $request->user()?->currentTeam?->subscription_tier ?? 'default';
            $perMinute = config("billing.api_rate_limits.{$tier}", config('billing.api_rate_limits.default'));

            return Limit::perMinute($perMinute)->by($request->user()?->id ?: $request->ip());
        });

        // Same ability strings as Jetstream's Sanctum personal-access-token
        // permissions (JetstreamServiceProvider::configurePermissions()) so
        // routes/api.php's `abilities:*` middleware checks work uniformly
        // whether the caller authenticated with a first-party Sanctum token
        // or a third-party app's Passport-issued OAuth token.
        Passport::tokensCan([
            'read' => 'View your data',
            'create' => 'Create new records on your behalf',
            'update' => 'Update your existing records',
            'delete' => 'Delete your records',
        ]);

        // Passport v13 ships no default consent-screen view (older versions
        // did) - without this binding, GET /oauth/authorize 500s with
        // "Target [AuthorizationViewResponse] is not instantiable."
        Passport::authorizationView('auth.oauth-authorize');

        // Gates the platform-wide /admin panel - distinct from the per-
        // organization superadmin/admin/user roles (JetstreamServiceProvider),
        // which are about permissions *within* an organization, not
        // operating the SaaS itself.
        Gate::define('access-admin-panel', fn (User $user) => $user->is_platform_admin);
    }
}
