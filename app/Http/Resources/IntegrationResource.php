<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domain\Integrations\Models\Integration
 */
class IntegrationResource extends JsonResource
{
    /**
     * Deliberately excludes access_token/refresh_token entirely, on top of
     * the model already hiding them - the public API should never be able
     * to leak a connected platform's credentials.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'account_id' => $this->account_id,
            'account_name' => $this->account_name,
            'avatar_url' => $this->avatar_url,
            'disabled' => $this->isDisabled(),
        ];
    }
}
