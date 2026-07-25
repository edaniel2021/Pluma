<?php

use App\Domain\Integrations\Providers\FakeProvider;
use App\Domain\Integrations\Providers\LinkedInProvider;
use App\Domain\Integrations\Providers\XProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Social Provider Registry
    |--------------------------------------------------------------------------
    |
    | The extensibility point for every publishing-target platform. Adding
    | a new one is "one class implementing SocialProviderContract + one
    | line here" - see App\Domain\Integrations\Support\SocialProviderManager.
    |
    | 'fake' has no real OAuth credentials and isn't offered as a connect
    | option in the UI - it exists for local dev/tests without needing
    | real API keys (Phase 3's FakeSocialPublisher, generalized).
    |
    */

    'providers' => [
        'linkedin' => LinkedInProvider::class,
        'x' => XProvider::class,
        'fake' => FakeProvider::class,
    ],

];
