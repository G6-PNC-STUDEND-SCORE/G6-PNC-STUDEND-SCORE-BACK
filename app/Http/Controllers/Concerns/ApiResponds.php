<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Small helper for the {success, data, message} JSON shape already used
 * informally across most of the API. Opt-in per controller — not added to
 * the shared base Controller, so adopting it elsewhere is a deliberate choice.
 */
trait ApiResponds
{
    protected function success(mixed $data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        $payload = ['success' => true];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $code);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function error(string $message, int $code = 422, mixed $errors = null): JsonResponse
    {
        $payload = ['message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code);
    }
}
