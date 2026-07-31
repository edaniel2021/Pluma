<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subscription Tiers
    |--------------------------------------------------------------------------
    |
    | Maps Postiz's subscription tiers (STANDARD/PRO/TEAM/ULTIMATE) to Stripe
    | Price IDs. `Organization::subscription('default')->swap($priceId)` uses
    | these for upgrades/downgrades; the Stripe webhook handler
    | (App\Domain\Billing\Http\Controllers\StripeWebhookController) reverse-
    | looks-up a tier from the price ID on subscription webhook events to
    | keep `organizations.subscription_tier` in sync.
    |
    */

    'tiers' => [
        'standard' => env('STRIPE_PRICE_STANDARD'),
        'pro' => env('STRIPE_PRICE_PRO'),
        'team' => env('STRIPE_PRICE_TEAM'),
        'ultimate' => env('STRIPE_PRICE_ULTIMATE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public API Rate Limits
    |--------------------------------------------------------------------------
    |
    | Requests/minute for routes/api.php's `v1` group (see the 'api'
    | RateLimiter defined in AppServiceProvider), keyed by the same tier
    | strings as `tiers` above. 'default' applies when
    | `organizations.subscription_tier` is null (no active subscription).
    |
    */

    'api_rate_limits' => [
        'default' => 30,
        'standard' => 60,
        'pro' => 300,
        'team' => 600,
        'ultimate' => 1200,
    ],

];
