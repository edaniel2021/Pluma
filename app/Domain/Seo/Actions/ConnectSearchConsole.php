<?php

namespace App\Domain\Seo\Actions;

use App\Domain\Seo\Models\SearchConsoleAccount;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Find-or-create on the connected Google account's email, mirroring
 * AbstractSocialProvider::connect()'s find-or-create shape - but keyed on
 * email rather than a provider+account_id pair, since there's only one
 * provider here and Google's own account identity is the natural key.
 */
class ConnectSearchConsole
{
    public function execute(SocialiteUser $socialiteUser): SearchConsoleAccount
    {
        return SearchConsoleAccount::updateOrCreate(
            ['google_email' => $socialiteUser->getEmail()],
            [
                'access_token' => $socialiteUser->token,
                'refresh_token' => $socialiteUser->refreshToken,
                'token_expires_at' => $socialiteUser->expiresIn ? now()->addSeconds($socialiteUser->expiresIn) : null,
                'disabled_at' => null,
            ]
        );
    }
}
