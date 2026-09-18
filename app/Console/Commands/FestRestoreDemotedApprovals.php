<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remediation for FestSchoolEventFeeService::demoteSiblingApprovals() (and its
 * batch-billing twin in FestRegistrationBatchFeeService) — before it was disabled
 * (2026-09-18), it flipped a school's already-approved registrations back to
 * 'submitted' whenever their fee balance fell short again, with NO exception for an
 * item whose results were already published (see that method's old docblock: "always
 * demote, no exception for results already published"). That's the one clearly-wrong
 * outcome — a registration silently un-approved underneath results that were already
 * published and locked in.
 *
 * Finds every 'fest.registration.demoted_unpaid' audit log entry for this Sahodaya
 * (or one event), keeps only the registration ids in it that are STILL 'submitted'
 * AND whose item has results_published_at set, and restores those to 'approved'.
 * Deliberately narrow: does NOT touch registrations demoted for an item that was
 * never published (those may genuinely still be underpaid — re-approve those by hand,
 * or via the normal registrations review page, once the fee is actually settled).
 *
 * Dry-run by default, same convention as fest:recalculate-sahodaya-fees.
 */
class FestRestoreDemotedApprovals extends Command
{
    protected $signature = 'fest:restore-demoted-approvals
        {--sahodaya= : Sahodaya tenant id or subdomain (required)}
        {--event= : Limit to one fest_events id (omit to check every event for the Sahodaya)}
        {--commit : Persist the restoration (defaults to dry-run)}';

    protected $description = 'Restore registrations that demoteSiblingApprovals() wrongly flipped back to submitted for an item whose results were already published';

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');
        $commit = (bool) $this->option('commit');

        if (! $sahodayaOpt) {
            $this->error('--sahodaya is required.');

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

        if (! $commit) {
            $this->info('Running in DRY-RUN mode. Use --commit to apply changes.');
        }

        $exitCode = self::SUCCESS;

        try {
            $tenant->run(function () use ($tenant, $eventOpt, $commit, &$exitCode) {
                $exitCode = $this->restore($tenant, $eventOpt, $commit);
            });
        } finally {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $exitCode;
    }

    private function restore(Tenant $tenant, ?string $eventOpt, bool $commit): int
    {
        $eventIds = FestEvent::where('tenant_id', $tenant->id)
            ->when($eventOpt, fn ($q) => $q->whereKey($eventOpt))
            ->pluck('id')
            ->all();

        if ($eventIds === []) {
            $this->warn($eventOpt ? "Event #{$eventOpt} not found in this tenant." : 'No events found in this tenant.');

            return self::SUCCESS;
        }

        // properties is a JSON array cast on the model — filtered in PHP rather than a
        // JSON-path DB query, since audit log volume for one action/tenant is small and
        // this keeps the query portable across the app's supported DB drivers.
        $logs = AuditLog::where('action', 'fest.registration.demoted_unpaid')
            ->get()
            ->filter(fn (AuditLog $log) => in_array($log->properties['event_id'] ?? null, $eventIds, true));

        $candidateIds = $logs
            ->flatMap(fn (AuditLog $log) => $log->properties['registration_ids'] ?? [])
            ->unique()
            ->values();

        if ($candidateIds->isEmpty()) {
            $this->info('No demotion log entries found for this tenant/event — nothing to check.');

            return self::SUCCESS;
        }

        $registrations = FestRegistration::whereIn('id', $candidateIds)
            ->where('status', 'submitted')
            ->with(['item:id,title,results_published_at', 'school:id,name'])
            ->get()
            ->filter(fn (FestRegistration $r) => $r->item?->results_published_at !== null)
            ->values();

        if ($registrations->isEmpty()) {
            $this->info("Checked {$candidateIds->count()} previously-demoted registration(s) — none are still 'submitted' under a published-results item.");

            return self::SUCCESS;
        }

        DB::beginTransaction();

        $rows = [];
        foreach ($registrations as $registration) {
            $registration->update(['status' => 'approved']);

            app(\App\Services\Audit\PlatformAuditLogger::class)->log(
                action: 'fest.registration.restored_after_demotion',
                description: "Registration #{$registration->id} restored to approved — it had been demoted while its item's results were already published",
                subject: $registration,
                properties: [
                    'event_id' => $registration->event_id,
                    'school_id' => $registration->school_id,
                    'item_id' => $registration->item_id,
                    'results_published_at' => optional($registration->item?->results_published_at)->toIso8601String(),
                ],
                category: 'finance',
            );

            $rows[] = [
                'registration_id' => $registration->id,
                'school' => $registration->school?->name ?? $registration->school_id,
                'item' => $registration->item?->title ?? "#{$registration->item_id}",
                'results_published_at' => $registration->item?->results_published_at?->format('Y-m-d H:i'),
            ];
        }

        if ($commit) {
            DB::commit();
        } else {
            DB::rollBack();
        }

        $this->table(['Registration', 'School', 'Item', 'Results published at'], $rows);

        $this->info($commit
            ? "Restored {$registrations->count()} registration(s) to approved."
            : "Would restore {$registrations->count()} registration(s) — re-run with --commit to apply.");

        return self::SUCCESS;
    }
}
