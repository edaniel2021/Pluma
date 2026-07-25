<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Support\AbstractSocialProvider;
use App\Domain\Integrations\Support\BadBodyException;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Facades\Http;

/**
 * X's Free API tier is not viable for production posting volume - paid
 * tiers gate real throughput. Flagged here since it's an operational
 * constraint to plan around, not a code concern.
 */
class XProvider extends AbstractSocialProvider
{
    public function key(): string
    {
        return 'x';
    }

    public function label(): string
    {
        return 'X (Twitter)';
    }

    public function socialiteDriver(): string
    {
        return 'x';
    }

    public function scopes(): array
    {
        // Beyond the x driver's default ['users.read', 'users.email', 'tweet.read'].
        // offline.access is what makes X issue a refresh_token at all.
        return ['tweet.write', 'offline.access'];
    }

    public function refreshToken(Integration $integration): void
    {
        if (! $integration->refresh_token) {
            $integration->forceFill(['disabled_at' => now()])->save();

            return;
        }

        $response = Http::asForm()
            ->withBasicAuth(config('services.x.client_id'), config('services.x.client_secret'))
            ->post('https://api.x.com/2/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $integration->refresh_token,
                'client_id' => config('services.x.client_id'),
            ]);

        if ($response->failed()) {
            $integration->forceFill(['disabled_at' => now()])->save();

            return;
        }

        $integration->forceFill([
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token') ?? $integration->refresh_token,
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 7200)),
        ])->save();
    }

    public function post(Integration $integration, Post $post): void
    {
        $response = $this->request($integration)
            ->post('https://api.x.com/2/tweets', [
                'text' => $post->content,
            ]);

        $this->assertSuccessful($response, $integration);

        if (! $response->json('data.id')) {
            throw new BadBodyException("X accepted the request but returned no tweet ID for post #{$post->id}.");
        }
    }
}
