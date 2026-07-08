<?php

namespace App\Services\State;

use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use Illuminate\Support\Facades\DB;

class StateQualifierIntakeService
{
    /** @param array<string, mixed> $payload */
    public function receive(string $idempotencyKey, array $payload, string $sourceTenantId): StateQualifierIntake
    {
        $existing = StateQualifierIntake::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($idempotencyKey, $payload, $sourceTenantId) {
            $intake = StateQualifierIntake::create([
                'state_program_id' => $payload['state_program_id'],
                'source_tenant_id' => $sourceTenantId,
                'source_event_id'  => $payload['source_event_id'] ?? 0,
                'idempotency_key'  => $idempotencyKey,
                'status'           => 'received',
                'payload'          => $payload,
                'payload_hash'     => hash('sha256', json_encode($payload)),
            ]);

            foreach ($payload['entries'] ?? [] as $entry) {
                StateQualifierEntry::create([
                    'intake_id'              => $intake->id,
                    'source_registration_id' => $entry['source_registration_id'] ?? null,
                    'source_participant_id'  => $entry['source_participant_id'] ?? null,
                    'school_id'              => $entry['school_id'],
                    'school_name'            => $entry['school_name'] ?? null,
                    'item_id'                => $entry['item_id'] ?? null,
                    'item_code'              => $entry['item_code'] ?? null,
                    'item_name'              => $entry['item_name'] ?? null,
                    'student_name'           => $entry['student_name'],
                    'class_name'             => $entry['class_name'] ?? null,
                    'position'               => $entry['position'] ?? null,
                    'grade'                  => $entry['grade'] ?? null,
                    'points'                 => $entry['points'] ?? 0,
                    'partition_key'          => $entry['partition_key'] ?? null,
                    'qualifier_type'         => $entry['qualifier_type'] ?? 'regional_winner',
                    'status'                 => 'pending',
                    'meta'                   => $entry,
                ]);
            }

            return $intake;
        });
    }

    public function approve(StateQualifierIntake $intake, ?int $reviewedBy = null, ?string $notes = null): StateQualifierIntake
    {
        $intake->update([
            'status'       => 'approved',
            'reviewed_by'  => $reviewedBy,
            'reviewed_at'  => now(),
            'review_notes' => $notes,
        ]);

        StateQualifierEntry::where('intake_id', $intake->id)->update(['status' => 'approved']);

        return $intake->fresh();
    }
}
