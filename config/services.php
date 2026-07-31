<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', env('APP_URL').'/auth/github/callback'),
    ],

    // Publishing-target integrations (App\Domain\Integrations), distinct
    // from the app-login providers above.
    'linkedin-openid' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('LINKEDIN_REDIRECT_URI', env('APP_URL').'/integrations/linkedin/callback'),
    ],

    'x' => [
        'client_id' => env('X_CLIENT_ID'),
        'client_secret' => env('X_CLIENT_SECRET'),
        'redirect' => env('X_REDIRECT_URI', env('APP_URL').'/integrations/x/callback'),
    ],

    // Facebook Pages and Instagram (via its linked Page) both go through
    // the same Meta app, so INSTAGRAM_CLIENT_ID/SECRET default to the
    // Facebook ones - only set them separately if using a different app.
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', env('APP_URL').'/integrations/facebook/callback'),
    ],

    'instagram-facebook' => [
        'client_id' => env('INSTAGRAM_CLIENT_ID', env('FACEBOOK_CLIENT_ID')),
        'client_secret' => env('INSTAGRAM_CLIENT_SECRET', env('FACEBOOK_CLIENT_SECRET')),
        'redirect' => env('INSTAGRAM_REDIRECT_URI', env('APP_URL').'/integrations/instagram/callback'),
    ],

    // Defaults to the same Google OAuth app as the 'google' *login* driver
    // above - only set YOUTUBE_CLIENT_ID/SECRET separately if you want a
    // distinct Google Cloud project for the YouTube Data API.
    'youtube' => [
        'client_id' => env('YOUTUBE_CLIENT_ID', env('GOOGLE_CLIENT_ID')),
        'client_secret' => env('YOUTUBE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET')),
        'redirect' => env('YOUTUBE_REDIRECT_URI', env('APP_URL').'/integrations/youtube/callback'),
    ],

    // Used by App\Domain\Agents\Support\FalService for AI image generation,
    // as a faster/cheaper alternative to OpenAI's Images API. No official
    // client package exists, so this is called directly via the Http facade.
    'fal' => [
        'key' => env('FAL_KEY'),
    ],

];
