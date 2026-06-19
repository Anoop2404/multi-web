<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class ApiController extends Controller
{
    protected function ok(mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        $payload = ['data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    protected function message(string $message, int $status = 200, mixed $data = null): JsonResponse
    {
        $payload = ['message' => $message];
        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }
}
