<?php

namespace App\Http\Controllers;

use App\Domain\Integrations\Contracts\SocialProviderContract;
use App\Domain\Integrations\Support\SocialProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Generic OAuth connect/callback for publishing-target integrations
 * (Instagram/Facebook/LinkedIn/etc.) - distinct from SocialAuthController,
 * which is app *login* via Google/GitHub. One route pair handles every
 * registered provider; the per-platform difference lives entirely in each
 * provider's socialiteDriver()/scopes()/connect().
 */
class IntegrationConnectController extends Controller
{
    public function __construct(protected SocialProviderManager $providers)
    {
    }

    public function redirect(string $provider): RedirectResponse
    {
        $driver = $this->resolveConnectableProvider($provider);

        return Socialite::driver($driver->socialiteDriver())
            ->scopes($driver->scopes())
            ->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $driver = $this->resolveConnectableProvider($provider);

        $socialiteUser = Socialite::driver($driver->socialiteDriver())->user();

        $driver->connect($socialiteUser);

        return redirect()->route('integrations.index')
            ->banner("Connected {$driver->label()}.");
    }

    protected function resolveConnectableProvider(string $provider): SocialProviderContract
    {
        // 'fake' has no real OAuth credentials and isn't a connect option.
        if ($provider === 'fake' || ! array_key_exists($provider, $this->providers->available())) {
            throw new NotFoundHttpException;
        }

        return $this->providers->driver($provider);
    }
}
