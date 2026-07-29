<?php

use App\Domain\Integrations\Providers\FacebookProvider;
use App\Domain\Integrations\Providers\FakeProvider;
use App\Domain\Integrations\Providers\InstagramProvider;
use App\Domain\Integrations\Providers\LinkedInProvider;
use App\Domain\Integrations\Providers\XProvider;
use App\Domain\Integrations\Providers\YouTubeProvider;

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
    | Snapchat is deliberately not here: it has no public API for organic
    | post scheduling, only the Marketing API for paid ad creative - a
    | different feature, skipped per product decision. WhatsApp is also
    | not here: its Business Cloud API is targeted broadcast messaging to
    | opted-in contacts, not a "post" at all - see App\Domain\WhatsApp
    | instead, which isn't part of this registry or the Launches
    | calendar/composer.
    |
    */

    'providers' => [
        'linkedin' => LinkedInProvider::class,
        'x' => XProvider::class,
        'facebook' => FacebookProvider::class,
        'instagram' => InstagramProvider::class,
        'youtube' => YouTubeProvider::class,
        'fake' => FakeProvider::class,
    ],

];
