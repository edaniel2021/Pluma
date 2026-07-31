<?php

use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\PostController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
 * Public API v1 - for developers/integrations authenticating with either a
 * first-party Sanctum personal access token (Jetstream's API Tokens UI) or
 * a third-party OAuth app's Passport-issued token (App\Livewire\Developers,
 * Phase 7). Tenancy needs no extra scoping code here: BelongsToOrganization's
 * global scope resolves the token owner's currentTeam exactly like a web
 * session, so these controllers query Post::/Integration:: unscoped-looking
 * but are automatically restricted to the caller's organization.
 */
Route::prefix('v1')->middleware(['auth:sanctum,api', 'throttle:api'])->group(function () {
    Route::get('/integrations', [IntegrationController::class, 'index'])->middleware('abilities:read');
    Route::get('/integrations/{integration}', [IntegrationController::class, 'show'])->middleware('abilities:read');

    Route::get('/posts', [PostController::class, 'index'])->middleware('abilities:read');
    Route::post('/posts', [PostController::class, 'store'])->middleware('abilities:create');
    Route::get('/posts/{post}', [PostController::class, 'show'])->middleware('abilities:read');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->middleware('abilities:update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->middleware('abilities:delete');
});
