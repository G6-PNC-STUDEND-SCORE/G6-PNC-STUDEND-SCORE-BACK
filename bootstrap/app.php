<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // This is an API-only backend with no 'login' web route. Without this, the
        // "auth" middleware's default redirectTo() calls route('login') for any
        // request that doesn't look like an XHR/JSON call (e.g. no Accept header),
        // which throws RouteNotFoundException and turns every expired/missing-token
        // request into a 500 instead of a clean 401 JSON response.
        $middleware->redirectGuestsTo(fn () => null);

        // Register permission middleware alias for use in routes
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'role'       => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Return JSON for authentication exceptions instead of redirecting to login route
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        });
    })->create();
