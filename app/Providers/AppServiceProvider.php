<?php

namespace App\Providers;

use App\Domain\Organization\Models\Organization;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

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
    }
}
