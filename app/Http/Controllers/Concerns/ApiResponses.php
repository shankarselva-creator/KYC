<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

trait ApiResponses
{
    protected function ok(mixed $data = null, array $meta = []): JsonResponse
    {
        return response()->json([
            'data'  => $data,
            'meta'  => (object) $meta,
            'error' => null,
        ]);
    }

    protected function fail(string $code, string $message, int $status = 400, array $details = []): JsonResponse
    {
        return response()->json([
            'data'  => null,
            'error' => [
                'code'    => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }
}
