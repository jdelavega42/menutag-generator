<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureGuestToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Guest ownership (spec §7.2): every web visitor gets a session
        // guest_token UUID on the first visit — appended to the whole web
        // group so guest history works on any page. Never the session ID:
        // it rotates on regenerate()/login. See EnsureGuestToken docblock.
        $middleware->web(append: [
            EnsureGuestToken::class,
        ]);

        $middleware->alias([
            'guest.token' => EnsureGuestToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
