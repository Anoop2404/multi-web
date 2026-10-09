<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\FestSubstitutionRequest;
use App\Support\AuditLogCatalog;
use App\Support\FestPageActivity;
use Illuminate\Console\Command;

class FestBackfillRegistrationLogs extends Command
{
    protected $signature = 'fest:backfill-registration-logs
        {event? : Optional FestEvent ID to restrict backfilling}
        {--dry-run : Output actions without writing to audit_logs}';

    protected $description = 'Backfill missing audit logs for fest registrations and substitutions';

    public function handle(): int
    {
        $eventId = $this->argument('event');
        $dryRun = (bool) $this->option('dry-run');

        $eventQuery = FestEvent::query();
        if ($eventId) {
            $eventQuery->where('id', $eventId);
        }
        $events = $eventQuery->get();

        if ($events->isEmpty()) {
            $this->error('No matching FestEvent found.');

            return self::FAILURE;
        }

        $regMorph = (new FestRegistration)->getMorphClass();
        $subMorph = (new FestSubstitutionRequest)->getMorphClass();
        $totalCreatedReg = 0;
        $totalCreatedSub = 0;
        $processedRegIds = [];
        $processedSubIds = [];

        foreach ($events as $event) {
            $reportableIds = $event->reportableEventIds();

            $registrations = FestRegistration::whereIn('event_id', $reportableIds)
                ->with(['item', 'school', 'participants.student', 'participants.teacher', 'participants.group'])
                ->get();

            $this->info("Checking {$registrations->count()} registrations for Event #{$event->id} ({$event->title})...");

            foreach ($registrations as $reg) {
                if (isset($processedRegIds[$reg->id])) {
                    continue;
                }
                $processedRegIds[$reg->id] = true;

                // Check if an audit log already exists for this registration
                $hasLog = AuditLog::whereIn('tenant_id', [$event->tenant_id, $reg->school_id])->where(function ($query) use ($regMorph, $reg) {
                    $query->where(function ($q) use ($regMorph, $reg) {
                        $q->where('subject_type', $regMorph)->where('subject_id', (string) $reg->id);
                    })
                        ->orWhere('properties->registration_id', $reg->id)
                        ->orWhere('properties->registration_id', (string) $reg->id);
                })->exists();

                if ($hasLog) {
                    continue;
                }

                $schoolName = $reg->school?->name;
                $itemTitle = $reg->item?->title;
                $participantNames = $reg->participants
                    ->map(fn ($p) => $p->student?->name ?? $p->teacher?->name ?? $p->group?->team_name)
                    ->filter()
                    ->unique()
                    ->implode(', ');

                $suffixParts = array_filter([$itemTitle, $schoolName, $participantNames]);
                $suffix = $suffixParts !== [] ? ' ('.implode(' — ', $suffixParts).')' : '';

                $action = match ($reg->status) {
                    'approved' => 'fest.registration.approved',
                    'rejected' => 'fest.registration.rejected',
                    'cancelled', 'withdrawn' => 'fest.registration.cancelled',
                    default => 'fest.registration.submitted',
                };

                $description = match ($reg->status) {
                    'approved' => "Fest registration #{$reg->id} approved{$suffix}",
                    'rejected' => "Fest registration #{$reg->id} rejected{$suffix}",
                    'cancelled', 'withdrawn' => "Fest registration #{$reg->id} cancelled{$suffix}",
                    default => "Fest registration #{$reg->id} submitted{$suffix}",
                };

                $logData = [
                    'tenant_id' => $event->tenant_id,
                    'action' => $action,
                    'description' => $description,
                    'subject_type' => $regMorph,
                    'subject_id' => (string) $reg->id,
                    'properties' => [
                        'event_id' => $reg->event_id,
                        'school_id' => $reg->school_id,
                        'school' => $schoolName,
                        'item_id' => $reg->item_id,
                        'item_title' => $itemTitle,
                        'participant' => $participantNames,
                        'page' => FestPageActivity::REGISTRATIONS,
                        'backfilled' => true,
                    ],
                    'created_at' => $reg->submitted_at ?? $reg->created_at,
                    'updated_at' => $reg->updated_at,
                ];

                if (! $dryRun) {
                    (new AuditLog)->getConnection()->table('audit_logs')->insert([
                        'tenant_id' => $logData['tenant_id'],
                        'action' => $logData['action'],
                        'category' => AuditLogCatalog::categoryForAction($logData['action']),
                        'description' => $logData['description'],
                        'subject_type' => $logData['subject_type'],
                        'subject_id' => $logData['subject_id'],
                        'properties' => json_encode($logData['properties']),
                        'created_at' => $logData['created_at'],
                        'updated_at' => $logData['updated_at'],
                    ]);
                }
                $totalCreatedReg++;
            }

            // Check substitutions
            $substitutionRequests = FestSubstitutionRequest::whereIn('event_id', $reportableIds)
                ->with(['registration.item', 'school', 'originalParticipant.student', 'replacementParticipant.student', 'replacementStudent'])
                ->get();

            foreach ($substitutionRequests as $sub) {
                if (isset($processedSubIds[$sub->id])) {
                    continue;
                }
                $processedSubIds[$sub->id] = true;

                $hasLog = AuditLog::whereIn('tenant_id', [$event->tenant_id, $sub->school_id])->where(function ($query) use ($subMorph, $sub) {
                    $query->where(function ($q) use ($subMorph, $sub) {
                        $q->where('subject_type', $subMorph)->where('subject_id', (string) $sub->id);
                    })
                        ->orWhere('properties->substitution_request_id', $sub->id)
                        ->orWhere('properties->substitution_request_id', (string) $sub->id);
                })->exists();

                if ($hasLog) {
                    continue;
                }

                $originalName = $sub->originalParticipant?->student?->name ?? "participant #{$sub->original_participant_id}";
                $replacementName = $sub->replacementParticipant?->student?->name ?? $sub->replacementStudent?->name ?? 'new participant';
                $itemTitle = $sub->registration?->item?->title;
                $schoolName = $sub->school?->name;

                $suffixParts = array_filter([$itemTitle, $schoolName]);
                $suffix = $suffixParts !== [] ? ' ('.implode(' — ', $suffixParts).')' : '';

                $action = match ($sub->status) {
                    'approved' => 'fest.substitution.approved',
                    'rejected' => 'fest.substitution.rejected',
                    'cancelled' => 'fest.substitution.cancelled',
                    default => 'fest.substitution.requested',
                };

                $description = match ($sub->status) {
                    'approved' => "Substitution request #{$sub->id} approved for registration #{$sub->registration_id}: {$originalName} -> {$replacementName}{$suffix}",
                    'rejected' => "Substitution request #{$sub->id} rejected for registration #{$sub->registration_id}{$suffix}",
                    'cancelled' => "Substitution request #{$sub->id} cancelled for registration #{$sub->registration_id}{$suffix}",
                    default => "Substitution requested for registration #{$sub->registration_id}: {$originalName} -> {$replacementName}{$suffix}",
                };

                $logData = [
                    'tenant_id' => $event->tenant_id,
                    'action' => $action,
                    'description' => $description,
                    'subject_type' => $subMorph,
                    'subject_id' => (string) $sub->id,
                    'properties' => [
                        'event_id' => $sub->event_id,
                        'school_id' => $sub->school_id,
                        'school' => $schoolName,
                        'registration_id' => $sub->registration_id,
                        'substitution_request_id' => $sub->id,
                        'item_id' => $sub->registration?->item_id,
                        'item_title' => $itemTitle,
                        'original_participant_id' => $sub->original_participant_id,
                        'replacement_participant_id' => $sub->replacement_participant_id,
                        'replacement_student_id' => $sub->replacement_student_id,
                        'reason' => $sub->reason,
                        'page' => FestPageActivity::REGISTRATIONS,
                        'backfilled' => true,
                    ],
                    'created_at' => $sub->created_at,
                    'updated_at' => $sub->updated_at,
                ];

                if (! $dryRun) {
                    (new AuditLog)->getConnection()->table('audit_logs')->insert([
                        'tenant_id' => $logData['tenant_id'],
                        'action' => $logData['action'],
                        'category' => AuditLogCatalog::categoryForAction($logData['action']),
                        'description' => $logData['description'],
                        'subject_type' => $logData['subject_type'],
                        'subject_id' => $logData['subject_id'],
                        'properties' => json_encode($logData['properties']),
                        'created_at' => $logData['created_at'],
                        'updated_at' => $logData['updated_at'],
                    ]);
                }
                $totalCreatedSub++;
            }
        }

        $this->info("Completed. Backfilled {$totalCreatedReg} registration logs and {$totalCreatedSub} substitution logs. Dry run: ".($dryRun ? 'YES' : 'NO'));

        return self::SUCCESS;
    }
}
