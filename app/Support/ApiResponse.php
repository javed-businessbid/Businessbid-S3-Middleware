<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Build a consistent API error payload.
     *
     * @param  array<string, array<int, string>>  $fields
     */
    public static function error(
        string $code,
        string $message,
        array $fields = [],
        int $status = 400,
    ): JsonResponse {
        $payload = [
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'fields' => $fields !== [] ? $fields : null,
            ], static fn ($value) => $value !== null),
        ];

        return response()->json($payload, $status);
    }
}
