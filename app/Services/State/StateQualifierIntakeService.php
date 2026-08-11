<?php

namespace App\Services\State;

use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StateQualifierIntakeService
{
    /** @param array<string, mixed> $payload */
    public function receive(string $idempotencyKey, array $payload, string $sourceTenantId): StateQualifierIntake
    {
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $existing = StateQualifierIntake::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if (! hash_equals((string) $existing->payload_hash, $payloadHash)
                || ! hash_equals((string) $existing->source_tenant_id, $sourceTenantId)) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'This idempotency key was already used for a different qualifier payload or source tenant.',
                ]);
            }

            return $existing;
        }

        return DB::connection('state')->transaction(function () use ($idempotencyKey, $payload, $sourceTenantId, $payloadHash) {
            $intake = StateQualifierIntake::create([
                'state_program_id' => $payload['state_program_id'],
                'source_tenant_id' => $sourceTenantId,
                'source_event_id'  => $payload['source_event_id'] ?? 0,
                'idempotency_key'  => $idempotencyKey,
                'status'           => 'received',
                'payload'          => $payload,
                'payload_hash'     => $payloadHash,
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
        if (in_array($intake->status, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['intake' => 'This qualifier intake has already been finalized.']);
        }

        DB::connection('state')->transaction(function () use ($intake, $reviewedBy, $notes) {
            StateQualifierEntry::where('intake_id', $intake->id)
                ->where('status', 'pending')
                ->update(['status' => 'approved']);

            $hasApprovedEntries = StateQualifierEntry::where('intake_id', $intake->id)
                ->where('status', 'approved')
                ->exists();

            $intake->update([
                'status'       => $hasApprovedEntries ? 'approved' : 'rejected',
                'reviewed_by'  => $reviewedBy,
                'reviewed_at'  => now(),
                'review_notes' => $notes,
            ]);
        });

        return $intake->fresh();
    }

    public function reviewEntry(StateQualifierIntake $intake, StateQualifierEntry $entry, string $status): StateQualifierEntry
    {
        if ($entry->intake_id !== $intake->id) {
            throw ValidationException::withMessages(['entry' => 'Qualifier entry does not belong to this intake.']);
        }
        if (! in_array($status, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => 'Entry status must be approved or rejected.']);
        }
        if (in_array($intake->status, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['entry' => 'This intake has already been finalized.']);
        }

        $entry->update(['status' => $status]);

        return $entry->fresh();
    }
}
