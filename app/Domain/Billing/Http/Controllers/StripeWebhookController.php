<?php

namespace App\Domain\Billing\Http\Controllers;

use App\Domain\Billing\Support\SubscriptionTierResolver;
use Laravel\Cashier\Http\Controllers\WebhookController;

/**
 * Extends Cashier's default webhook handling to keep
 * `organizations.subscription_tier` in sync with the organization's active
 * Stripe subscription, so feature-gating checks can read a plain column
 * instead of hitting Stripe/Cashier tables on every request.
 */
class StripeWebhookController extends WebhookController
{
    protected function handleCustomerSubscriptionCreated(array $payload)
    {
        $response = parent::handleCustomerSubscriptionCreated($payload);

        $this->syncSubscriptionTier($payload);

        return $response;
    }

    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        $response = parent::handleCustomerSubscriptionUpdated($payload);

        $this->syncSubscriptionTier($payload);

        return $response;
    }

    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        $response = parent::handleCustomerSubscriptionDeleted($payload);

        if ($organization = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            $organization->forceFill(['subscription_tier' => null])->save();
        }

        return $response;
    }

    protected function syncSubscriptionTier(array $payload): void
    {
        $organization = $this->getUserByStripeId($payload['data']['object']['customer']);

        if (! $organization) {
            return;
        }

        $items = $payload['data']['object']['items']['data'] ?? [];
        $priceId = count($items) === 1 ? $items[0]['price']['id'] : null;

        $tier = app(SubscriptionTierResolver::class)->fromPriceId($priceId);

        $organization->forceFill(['subscription_tier' => $tier])->save();
    }
}
