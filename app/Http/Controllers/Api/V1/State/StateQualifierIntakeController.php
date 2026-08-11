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
            'payload.source_event_id'  => 'required|integer|min:1',
            'payload.entries'          => 'required|array|min:1',
            'payload.entries.*.source_registration_id' => 'nullable|string|max:128',
            'payload.entries.*.source_participant_id' => 'nullable|string|max:128',
            'payload.entries.*.school_id' => 'required|string|max:128',
            'payload.entries.*.school_name' => 'nullable|string|max:255',
            'payload.entries.*.item_id' => 'required|uuid',
            'payload.entries.*.item_code' => 'required|string|max:64',
            'payload.entries.*.item_name' => 'nullable|string|max:255',
            'payload.entries.*.student_name' => 'required|string|max:255',
            'payload.entries.*.class_name' => 'nullable|string|max:64',
            'payload.entries.*.position' => 'required|integer|min:1|max:10',
            'payload.entries.*.grade' => 'nullable|string|max:8',
            'payload.entries.*.points' => 'nullable|numeric|min:0',
            'payload.entries.*.partition_key' => 'nullable|string|max:64',
            'payload.entries.*.qualifier_type' => 'nullable|string|max:32',
            'payload.entries.*.participant_type' => 'nullable|in:individual,pair,trio,group,team',
            'payload.entries.*.participants' => 'nullable|array|min:1',
            'payload.entries.*.participants.*.source_participant_id' => 'nullable|string|max:128',
            'payload.entries.*.participants.*.student_name' => 'required_with:payload.entries.*.participants|string|max:255',
            'payload.entries.*.participants.*.class_name' => 'nullable|string|max:64',
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
