<?php

namespace App\Http\Controllers;

use App\Domain\Auth\Actions\SocialLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SocialAuthController extends Controller
{
    /**
     * Providers supported for app login (Google/GitHub), distinct from the
     * much longer list of social-media *publishing* integrations planned
     * for later phases (Instagram, Facebook, LinkedIn, etc.).
     *
     * @var string[]
     */
    protected array $allowedProviders = ['google', 'github'];

    public function redirect(string $provider): RedirectResponse
    {
        $this->ensureProviderIsAllowed($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider, SocialLoginService $socialLogin): RedirectResponse
    {
        $this->ensureProviderIsAllowed($provider);

        $socialiteUser = Socialite::driver($provider)->user();

        try {
            $user = $socialLogin->findOrCreateUser($provider, $socialiteUser);
        } catch (RuntimeException $e) {
            return redirect()->route('login')->withErrors(['email' => $e->getMessage()]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(config('fortify.home'));
    }

    protected function ensureProviderIsAllowed(string $provider): void
    {
        if (! in_array($provider, $this->allowedProviders, true)) {
            throw new NotFoundHttpException;
        }
    }
}
