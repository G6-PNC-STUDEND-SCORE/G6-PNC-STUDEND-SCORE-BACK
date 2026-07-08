<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that checks if the authenticated user has a specific permission.
 *
 * Usage in routes:
 *   Route::get('/students', ...)->middleware('permission:view-students');
 *   Route::post('/students', ...)->middleware('permission:create-students');
 *
 * Admin users automatically bypass all permission checks.
 */
class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Admin bypass — always allowed
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // Check the Gate for this permission
        if (!Gate::allows($permission)) {
            return response()->json([
                'message' => 'Forbidden. You do not have the required permission: ' . $permission,
            ], 403);
        }

        return $next($request);
    }
}