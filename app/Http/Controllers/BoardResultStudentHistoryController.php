<?php

namespace App\Http\Controllers;

use App\Services\BoardResults\BoardResultStudentHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardResultStudentHistoryController extends Controller
{
    public function sahodayaHistory(Request $request, string $sahodayaId, BoardResultStudentHistoryService $service): JsonResponse
    {
        $query = $request->string('query')->trim()->toString();
        if (strlen($query) < 2) {
            return response()->json(['query' => $query, 'matches' => []]);
        }

        $result = $service->search($query, sahodayaId: $sahodayaId);

        return response()->json($result);
    }

    public function schoolHistory(Request $request, string $tenantId, BoardResultStudentHistoryService $service): JsonResponse
    {
        $query = $request->string('query')->trim()->toString();
        if (strlen($query) < 2) {
            return response()->json(['query' => $query, 'matches' => []]);
        }

        $result = $service->search($query, tenantId: $tenantId);

        return response()->json($result);
    }
}
