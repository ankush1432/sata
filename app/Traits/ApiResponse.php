<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Standard success JSON envelope.
     */
    protected function successResponse(mixed $data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data ?? (object) [],
        ], $statusCode);
    }

    /**
     * Standard error JSON envelope.
     */
    protected function errorResponse(string $message = 'Error', mixed $errors = null, int $statusCode = 400, ?string $code = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'errors' => $errors ?? (object) [],
        ];

        if ($code !== null) {
            $payload['code'] = $code;
            $payload['error_code'] = $code;
        }

        return response()->json($payload, $statusCode);
    }
}
