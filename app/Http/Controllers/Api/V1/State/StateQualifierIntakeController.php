<?php

namespace App\Http\Controllers\Api\V1\State;

use App\Http\Controllers\Controller;
use App\Models\FestStateProgram;
use App\Models\StateDomain;
use App\Services\State\StateQualifierIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StateQualifierIntakeController extends Controller
{
    public function store(Request $request, StateQualifierIntakeService $intakeService): JsonResponse
    {
        $domain = $this->authorizeClient($request);

        $data = $request->validate([
            'idempotency_key' => 'required|string|max:128',
            'payload'         => 'required|array',
            'payload.state_program_id' => 'required|uuid',
            'payload.entries'          => 'array',
        ]);

        $program = FestStateProgram::find($data['payload']['state_program_id']);
        abort_if(! $program || $program->state_domain_id !== $domain->id, 403, 'State client is not allowed to submit this program.');

        $sourceTenantId = (string) ($data['payload']['source_tenant_id'] ?? $request->header('X-Source-Tenant-Id', 'unknown'));

        $intake = $intakeService->receive(
            $data['idempotency_key'],
            $data['payload'],
            $sourceTenantId,
        );

        return response()->json([
            'intake_id' => $intake->id,
            'status'    => $intake->status,
            'entries'   => $intake->entries()->count(),
        ], $intake->wasRecentlyCreated ? 201 : 200);
    }

    private function authorizeClient(Request $request): StateDomain
    {
        $clientId = (string) $request->header('X-State-Client-Id', '');
        $secret = (string) $request->header('X-State-Client-Secret', '');

        abort_if($clientId === '' || $secret === '', 401, 'Missing state API credentials.');

        $domain = StateDomain::query()
            ->where('api_client_id', $clientId)
            ->where('status', 'active')
            ->first();

        abort_if(! $domain || ! $domain->api_client_secret_hash, 401, 'Invalid state API credentials.');
        abort_unless(Hash::check($secret, $domain->api_client_secret_hash), 401, 'Invalid state API credentials.');

        return $domain;
    }
}
