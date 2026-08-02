<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the reverse proxy (Apache staging vhost, Caddy in production)
        // sitting in front of this container - without this, Laravel never
        // sees the original request as HTTPS (the proxy-to-container hop is
        // plain HTTP), so url()/asset()/redirects all generate http:// links
        // even though the browser is on https://. `at: '*'` rather than a
        // specific IP because the proxy connects through Docker's port
        // mapping, where the source IP Laravel sees is an unpredictable
        // Docker-internal gateway address, not the proxy's real IP.
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);

        // Not auto-registered by Sanctum's service provider - needed to gate
        // public API v1 routes by personal-access-token ability (see
        // routes/api.php).
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        Integration::handles($exceptions);
    })->create();
