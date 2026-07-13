<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // For API / JSON requests, never redirect to a web login route.
        if ($request instanceof Request && ($request->expectsJson() || $request->is('api/*'))) {
            if (method_exists($e, 'getStatusCode') && $e->getStatusCode() === 302) {
                // Some auth failures may come through as redirects
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            if (method_exists($e, 'getStatusCode') && $e->getStatusCode() === 401) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // Preserve existing behavior for other API exceptions.
            return response()->json([
                'message' => $e->getMessage(),
            ], (int) ($e->getCode() ?: 500));
        }

        return parent::render($request, $e);
    }
}

