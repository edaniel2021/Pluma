<?php

namespace App\Domain\Integrations\Support;

use App\Domain\Integrations\Contracts\SocialProviderContract;
use InvalidArgumentException;

/**
 * Config-driven registry (config/social-providers.php), not filesystem
 * auto-discovery - adding a new platform is "one class + one config line."
 */
class SocialProviderManager
{
    public function driver(string $key): SocialProviderContract
    {
        $class = $this->available()[$key] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("No social provider registered for [{$key}].");
        }

        return app($class);
    }

    /**
     * @return array<string, class-string<SocialProviderContract>>
     */
    public function available(): array
    {
        return config('social-providers.providers', []);
    }
}
