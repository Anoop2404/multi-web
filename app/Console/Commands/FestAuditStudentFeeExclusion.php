<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Read-only diagnostic for FestSportsCompositeFeeService::calculate()'s per-student
 * registration-fee eligibility ($studentIds — see that method): for one school on one
 * event, lists every distinct student who has at least one registration at all, and
 * explains exactly why each one is or isn't counted toward the ₹{per_student_amount}
 * fee — allowed registration status, and whether every one of their items has its own
 * fee_amount override (hasOwnFee()), which would exclude them even with real
 * registrations. Built to reconcile a manually-tallied roster against a live total_due
 * that doesn't match: run this and compare the "counted" list's size against the
 * manual count, then check each row in "not counted" for the actual reason. Never
 * writes anything.
 */
class FestAuditStudentFeeExclusion extends Command
{
    protected $signature = 'fest:audit-student-fee-exclusion
        {--sahodaya= : Sahodaya tenant id or subdomain (required)}
        {--event= : fest_events id to check (required)}
        {--school= : School tenant id, or a case-insensitive substring of its name (required)}';

    protected $description = 'Read-only audit: why is/isn\'t each student on one school counted toward the kalolsavam_composite per-student registration fee?';

    private const ALLOWED_STATUSES = ['submitted', 'approved', 'pending_approval'];

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');
        $schoolOpt = $this->option('school');

        if (! $sahodayaOpt || ! $eventOpt || ! $schoolOpt) {
            $this->error('--sahodaya, --event, and --school are all required.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()
            ->where('type', 'sahodaya')
            ->where(function ($q) use ($sahodayaOpt) {
                $q->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
            })
            ->first();

        if (! $tenant) {
            $this->error("No matching Sahodaya tenant for '{$sahodayaOpt}'.");

            return self::FAILURE;
        }

        $exitCode = self::SUCCESS;

        try {
            $tenant->run(function () use ($eventOpt, $schoolOpt, &$exitCode) {
                $exitCode = $this->audit((int) $eventOpt, $schoolOpt);
            });
        } finally {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $exitCode;
    }

    private function audit(int $eventId, string $schoolOpt): int
    {
        $event = FestEvent::find($eventId);
        if (! $event) {
            $this->error("No fest_events row with id={$eventId} in this tenant.");

            return self::FAILURE;
        }

        $school = Tenant::where('type', 'school')
            ->where(function ($q) use ($schoolOpt) {
                $q->where('id', $schoolOpt)->orWhere('name', 'like', "%{$schoolOpt}%");
            })
            ->first();

        if (! $school) {
            $this->error("No school tenant matching '{$schoolOpt}'.");

            return self::FAILURE;
        }

        $this->info("Event #{$event->id} — {$event->title}");
        $this->info("School: {$school->name} ({$school->id})");
        $this->newLine();

        $eventIds = $event->reportableEventIds();

        // Every registration regardless of status, so a status-based exclusion is visible
        // rather than silently missing from the audit.
        $registrations = FestRegistration::whereIn('event_id', $eventIds)
            ->where('school_id', $school->id)
            ->with(['item', 'participants.student:id,name'])
            ->orderBy('id')
            ->get();

        $byStudent = [];
        foreach ($registrations as $registration) {
            $statusOk = in_array($registration->status, self::ALLOWED_STATUSES, true);
            $item = $registration->item;
            $hasOwnFee = $item?->fee_amount !== null;

            foreach ($registration->participants as $participant) {
                if ($participant->participant_role === 'standby' || ! $participant->student_id) {
                    continue;
                }

                $byStudent[$participant->student_id]['name'] ??= $participant->student?->name ?? $participant->student_id;
                $byStudent[$participant->student_id]['items'][] = [
                    'item' => $item?->title ?? "item_id={$registration->item_id}",
                    'status' => $registration->status,
                    'status_ok' => $statusOk,
                    'fee_amount' => $item?->fee_amount,
                    'has_own_fee' => $hasOwnFee,
                    'counts' => $statusOk && ! $hasOwnFee,
                ];
            }
        }

        $rows = [];
        $counted = 0;
        $notCounted = 0;

        foreach ($byStudent as $studentId => $data) {
            $countingItems = array_filter($data['items'], fn ($i) => $i['counts']);
            $isCounted = count($countingItems) > 0;
            $isCounted ? $counted++ : $notCounted++;

            $itemSummary = implode('; ', array_map(
                fn ($i) => $i['item'].' ['.$i['status'].($i['has_own_fee'] ? ', fee_amount='.$i['fee_amount'] : '').']',
                $data['items']
            ));

            $rows[] = [
                $studentId,
                $data['name'],
                $isCounted ? 'COUNTED' : 'not counted',
                count($data['items']),
                $itemSummary,
            ];
        }

        $this->table(['Student ID', 'Name', 'Toward ₹500 fee?', '# items', 'Items [status, override]'], $rows);

        $this->newLine();
        $this->info("Students counted toward the per-student fee: {$counted}");
        $this->info("Students with registrations but NOT counted: {$notCounted}");
        $this->line('A student is "not counted" when either every one of their registrations has a status outside '.implode('/', self::ALLOWED_STATUSES).', or every one of their items has its own fee_amount override.');

        return self::SUCCESS;
    }
}
