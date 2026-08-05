<?php

use App\Domain\Agents\Models\AgentThread;
use App\Domain\Billing\Http\Controllers\StripeWebhookController;
use App\Domain\Posts\Models\Post;
use App\Domain\Seo\Models\SeoWebsite;
use App\Domain\WhatsApp\Models\WhatsAppAccount;
use App\Http\Controllers\IntegrationConnectController;
use App\Http\Controllers\SearchConsoleConnectController;
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

    // Communications and Analytics & Reports are still future phases (see
    // CLAUDE.md's phased build order) - these stay placeholder landing
    // pages so the nav category isn't a dead link before each phase ships.
    // SEO is real now - see below.
    Route::get('/communications', function () {
        return view('communications.index');
    })->name('communications.index');

    Route::get('/analytics', function () {
        return view('analytics.index');
    })->name('analytics.index');

    Route::get('/seo', function () {
        return view('seo.index');
    })->name('seo.index');

    Route::get('/seo/websites/{website}', function (SeoWebsite $website) {
        return view('seo.analysis', ['website' => $website]);
    })->name('seo.websites.show');

    Route::get('/seo/search-console/redirect', [SearchConsoleConnectController::class, 'redirect'])
        ->name('search-console.redirect');
    Route::get('/seo/search-console/callback', [SearchConsoleConnectController::class, 'callback'])
        ->name('search-console.callback');

    // Platform-wide, not organization-scoped - see the is_platform_admin
    // migration docblock for why this is a separate gate from the per-org
    // superadmin/admin/user roles.
    Route::middleware('can:access-admin-panel')->prefix('admin')->group(function () {
        Route::get('/errors', function () {
            return view('admin.errors');
        })->name('admin.errors');

        Route::get('/stats', function () {
            return view('admin.stats');
        })->name('admin.stats');
    });
});
