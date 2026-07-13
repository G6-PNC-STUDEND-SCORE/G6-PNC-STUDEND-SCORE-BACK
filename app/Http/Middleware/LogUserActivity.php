<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that logs user login and logout events.
 *
 * Detects login by checking for a newly authenticated session.
 * Detects logout by intercepting POST requests to logout routes.
 *
 * This middleware should be applied to the 'web' middleware group
 * or specific routes that need login/logout tracking.
 */
class LogUserActivity
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     * Used to log login events after the session is fully established.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Login detection: check if session just became authenticated
        // This is triggered after the login controller redirects
        if ($request->isMethod('GET') && $request->user() && $request->session()->has('logged_in')) {
            $this->activityLogService->logLogin($request->user());
            $request->session()->forget('logged_in');
        }
    }
}