<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Models\SocialAccount;
use App\Domain\Organization\Actions\CreatePersonalOrganization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use RuntimeException;

/**
 * Postiz's AuthProviderManager equivalent: logs in an existing user or
 * auto-registers one from a Google/GitHub Socialite callback.
 */
class SocialLoginService
{
    public function __construct(protected CreatePersonalOrganization $createPersonalOrganization)
    {
    }

    public function findOrCreateUser(string $provider, SocialiteUser $socialiteUser): User
    {
        $account = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($account) {
            return $account->user;
        }

        $user = User::where('email', $socialiteUser->getEmail())->first();

        if (! $user) {
            if (config('features.registration_disabled')) {
                throw new RuntimeException('Registration is currently disabled.');
            }

            $user = DB::transaction(function () use ($socialiteUser) {
                return tap(User::create([
                    'name' => $socialiteUser->getName() ?: $socialiteUser->getNickname(),
                    'email' => $socialiteUser->getEmail(),
                    'password' => Hash::make(Str::random(40)),
                    'email_verified_at' => now(),
                ]), function (User $user) {
                    $this->createPersonalOrganization->create($user);
                });
            });
        }

        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialiteUser->getId(),
        ]);

        return $user;
    }
}
