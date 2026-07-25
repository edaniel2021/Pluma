<?php

namespace App\Domain\Billing\Support;

class SubscriptionTierResolver
{
    /**
     * Reverse-look-up a Postiz-style tier key (standard/pro/team/ultimate)
     * from a Stripe Price ID, using the mapping in config/billing.php.
     */
    public function fromPriceId(?string $stripePriceId): ?string
    {
        if (! $stripePriceId) {
            return null;
        }

        foreach (config('billing.tiers') as $tier => $priceId) {
            if ($priceId && $priceId === $stripePriceId) {
                return $tier;
            }
        }

        return null;
    }
}
