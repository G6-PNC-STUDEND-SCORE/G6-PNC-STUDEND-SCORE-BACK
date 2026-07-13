<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        // For API (and Postman), never try to redirect to a web login route.
        // Instead, Laravel will return 401/JSON handled by the auth system.
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        // Web requests (non-API) fall back to the login named route.
        return route('login');
    }
}
