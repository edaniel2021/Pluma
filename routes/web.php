<?php

use App\Domain\Agents\Models\AgentThread;
use App\Domain\Billing\Http\Controllers\StripeWebhookController;
use App\Domain\Posts\Models\Post;
use App\Domain\WhatsApp\Models\WhatsAppAccount;
use App\Http\Controllers\IntegrationConnectController;
use App\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Http\Controllers\PaymentController as CashierPaymentController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->name('social.callback');

// Same route names/paths Cashier registers by default (Cashier::ignoreRoutes()
// disables its own copies in AppServiceProvider) so anything calling
// route('cashier.webhook')/route('cashier.payment') keeps working.
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');
Route::get('/stripe/payment/{id}', [CashierPaymentController::class, 'show'])
    ->name('cashier.payment');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/posts', function () {
        return view('posts.index');
    })->name('posts.index');

    Route::get('/posts/create', function () {
        return view('posts.create');
    })->name('posts.create');

    Route::get('/posts/{post}/edit', function (Post $post) {
        return view('posts.edit', ['post' => $post]);
    })->name('posts.edit');

    Route::get('/media', function () {
        return view('media.index');
    })->name('media.index');

    Route::get('/integrations', function () {
        return view('integrations.index');
    })->name('integrations.index');

    Route::get('/integrations/{provider}/redirect', [IntegrationConnectController::class, 'redirect'])
        ->name('integrations.redirect');
    Route::get('/integrations/{provider}/callback', [IntegrationConnectController::class, 'callback'])
        ->name('integrations.callback');

    Route::get('/launches', function () {
        return view('launches.index');
    })->name('launches.index');

    Route::get('/launches/compose', function () {
        return view('launches.compose');
    })->name('launches.compose');

    Route::get('/launches/{post}/compose', function (Post $post) {
        return view('launches.compose', ['post' => $post]);
    })->name('launches.compose.edit');

    Route::get('/whatsapp', function () {
        return view('whatsapp.index');
    })->name('whatsapp.index');

    Route::get('/whatsapp/{account}/contacts', function (WhatsAppAccount $account) {
        return view('whatsapp.contacts', ['account' => $account]);
    })->name('whatsapp.contacts');

    Route::get('/whatsapp/{account}/broadcasts', function (WhatsAppAccount $account) {
        return view('whatsapp.broadcasts', ['account' => $account]);
    })->name('whatsapp.broadcasts');

    Route::get('/agents', function () {
        return view('agents.index');
    })->name('agents.index');

    Route::get('/agents/{thread}', function (AgentThread $thread) {
        return view('agents.show', ['thread' => $thread]);
    })->name('agents.show');

    Route::get('/developers', function () {
        return view('developers.index');
    })->name('developers.index');
});
