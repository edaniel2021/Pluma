<?php

use App\Domain\Billing\Http\Controllers\StripeWebhookController;
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
});
