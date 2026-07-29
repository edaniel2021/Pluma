<?php

namespace App\Providers;

use App\Domain\Organization\Models\Organization;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
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
    }
}
