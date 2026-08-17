<?php

namespace App\Console\Commands;

use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestQualification;
use App\Models\FestRegistration;
use App\Models\FestResult;
use App\Models\FestSchedule;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Read-only readiness check for converting a region-wise (conduct_mode=partitioned) Kalotsav
 * event into the phased_regional_billing structure (docs/MCS_FOUR_PHASE_COMPLETION_PLAN.md).
 * Never writes. Run before fest:configure-phased-structure — its verdict is what decides
 * whether a plain configure is safe (Branch A) or a relocation step is required first
 * (Branch B) before workflow_mode is ever flipped on an event with real data.
 */
class FestAuditRegionConversionReadiness extends Command
{
    protected $signature = 'fest:audit-region-conversion-readiness
        {--sahodaya= : Sahodaya tenant id or subdomain (required)}
        {--event= : Root fest_events id to check (required)}
        {--format=table : table|json}';

    protected $description = 'Read-only check of whether a region-wise event has operational/financial data that must be preserved before converting to phased_regional_billing';

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');

        if (! $sahodayaOpt || ! $eventOpt) {
            $this->error('Both --sahodaya and --event are required.');

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

        $report = null;

        try {
            $tenant->run(function () use ($eventOpt, &$report) {
                $report = $this->buildReport((int) $eventOpt);
            });
        } finally {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        if ($report === null) {
            $this->error("Event #{$eventOpt} not found for tenant {$tenant->id}.");

            return self::FAILURE;
        }

        $this->output($report, (string) $this->option('format'));

        return self::SUCCESS;
    }

    private function buildReport(int $eventId): ?array
    {
        $root = FestEvent::find($eventId);
        if (! $root) {
            return null;
        }

        $children = FestEvent::where('parent_event_id', $root->id)->get();
        $events = collect([$root])->merge($children);

        $rows = $events->map(fn (FestEvent $event) => $this->countsFor($event, $event->id === $root->id));
        $paidSchools = $this->paidHistorySchools($root);

        $totalRegistrations = $rows->sum('registrations');
        $totalSchools = $events
            ->flatMap(fn (FestEvent $e) => FestRegistration::where('event_id', $e->id)->pluck('school_id'))
            ->unique()
            ->count();

        return [
            'root' => [
                'id' => $root->id,
                'title' => $root->title,
                'conduct_mode' => $root->conduct_mode,
                'workflow_mode' => $root->workflow_mode,
                'phase_mode_enabled' => (bool) $root->phase_mode_enabled,
            ],
            'events' => $rows->values()->all(),
            'paid_schools' => $paidSchools->values()->all(),
            'verdict' => $totalRegistrations === 0 && $paidSchools->isEmpty()
                ? 'BRANCH A -- no operational or financial data found. Safe to configure the phased structure directly.'
                : "BRANCH B REQUIRED -- {$totalRegistrations} registration(s) across {$totalSchools} school(s) need relocation before workflow_mode can be enabled."
                    .($paidSchools->isNotEmpty() ? ' '.$paidSchools->count().' school(s) also have paid history requiring manual finance review.' : ''),
        ];
    }

    private function countsFor(FestEvent $event, bool $isRoot): array
    {
        return [
            'event_id' => $event->id,
            'title' => $event->title,
            'role' => $isRoot ? 'root' : ($event->partition_role ?? '—'),
            'region_id' => $event->region_id,
            'registrations' => FestRegistration::where('event_id', $event->id)->count(),
            'participants' => FestParticipant::where('event_id', $event->id)->count(),
            'marks' => FestMark::where('event_id', $event->id)->count(),
            'attendance' => FestAttendance::where('event_id', $event->id)->count(),
            'schedules' => FestSchedule::where('event_id', $event->id)->count(),
            'results' => FestResult::where('event_id', $event->id)->count(),
            'qualifications' => FestQualification::where('event_id', $event->id)->count(),
            'distinct_schools' => FestRegistration::where('event_id', $event->id)->pluck('school_id')->unique()->count(),
        ];
    }

    /** Schools with paid amounts or an approved receipt on the pre-batch fee rollup (registration_batch_id IS NULL). */
    private function paidHistorySchools(FestEvent $root): Collection
    {
        return FestSchoolEventFee::where('event_id', $root->id)
            ->whereNull('registration_batch_id')
            ->where(function ($q) {
                $q->where('amount_paid', '>', 0)
                    ->orWhereHas('feeReceipt', fn ($q2) => $q2->where('status', 'approved'));
            })
            ->get(['school_id', 'amount_paid', 'fee_receipt_id'])
            ->unique('school_id')
            ->map(fn ($f) => ['school_id' => $f->school_id, 'amount_paid' => (string) $f->amount_paid, 'fee_receipt_id' => $f->fee_receipt_id]);
    }

    private function output(array $report, string $format): void
    {
        if ($format === 'json') {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));

            return;
        }

        $this->info("Root event #{$report['root']['id']}: {$report['root']['title']}");
        $this->line("conduct_mode={$report['root']['conduct_mode']} workflow_mode={$report['root']['workflow_mode']} phase_mode_enabled=".($report['root']['phase_mode_enabled'] ? 'true' : 'false'));
        $this->newLine();

        $this->table(
            ['Event', 'Title', 'Role', 'Region', 'Regs', 'Participants', 'Marks', 'Attend.', 'Sched.', 'Results', 'Qualif.', 'Schools'],
            collect($report['events'])->map(fn ($r) => [
                $r['event_id'], $r['title'], $r['role'], $r['region_id'] ?? '—',
                $r['registrations'], $r['participants'], $r['marks'], $r['attendance'],
                $r['schedules'], $r['results'], $r['qualifications'], $r['distinct_schools'],
            ])->all()
        );

        if (! empty($report['paid_schools'])) {
            $this->newLine();
            $this->warn(count($report['paid_schools']).' school(s) with paid history on the pre-batch fee rollup:');
            $this->table(
                ['School', 'Amount paid', 'Fee receipt'],
                collect($report['paid_schools'])->map(fn ($s) => [$s['school_id'], $s['amount_paid'], $s['fee_receipt_id'] ?? '—'])->all()
            );
        }

        $this->newLine();
        if (str_starts_with($report['verdict'], 'BRANCH A')) {
            $this->info($report['verdict']);
        } else {
            $this->warn($report['verdict']);
        }
    }
}
