<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Integrations\Models\Integration;
use App\Http\Controllers\Controller;
use App\Http\Resources\IntegrationResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IntegrationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return IntegrationResource::collection(Integration::query()->latest()->get());
    }

    public function show(Integration $integration): IntegrationResource
    {
        return new IntegrationResource($integration);
    }
}
