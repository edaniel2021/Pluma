<?php

namespace App\Domain\Agents\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin Http-facade wrapper for FAL.ai - no official PHP/Laravel client
 * package exists, and FAL's synchronous "fal.run" endpoint is a single
 * plain POST, so a full SDK would be overkill.
 */
class FalService
{
    public function isConfigured(): bool
    {
        return filled(config('services.fal.key'));
    }

    public function generateImage(string $prompt): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('FAL_KEY is not configured.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Key '.config('services.fal.key'),
        ])->post('https://fal.run/'.config('agents.fal_image_model'), [
            'prompt' => $prompt,
        ])->throw();

        return $response->json('images.0.url');
    }
}
