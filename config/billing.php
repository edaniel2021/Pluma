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

];
