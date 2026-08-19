<?php

namespace App\Console\Commands;

use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestEventItem;
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
        {--format=table : table|json}
        {--item-breakdown : Also list every item per legacy region child with its registration/participant/chest-number counts, for building a real item_phase_map (Branch B)}';

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

        $itemBreakdown = (bool) $this->option('item-breakdown');
        $report = null;

        try {
            $tenant->run(function () use ($eventOpt, $itemBreakdown, &$report) {
                $report = $this->buildReport((int) $eventOpt, $itemBreakdown);
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

    private function buildReport(int $eventId, bool $itemBreakdown = false): ?array
    {
        $root = FestEvent::find($eventId);
        if (! $root) {
            return null;
        }

        $children = FestEvent::where('parent_event_id', $root->id)->get();
        $events = collect([$root])->merge($children);

        $rows = $events->map(fn (FestEvent $event) => $this->countsFor($event, $event->id === $root->id));
        $paidSchools = $this->paidHistorySchools($root);
        $items = $itemBreakdown ? $this->itemBreakdown($children) : null;

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
            'items' => $items,
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

    /**
     * Every item on each legacy region child (only children carrying partition_role with no
     * source_phase_id yet — a fresh phase leaf's own items aren't part of this decision),
     * with real registration/participant/chest-number counts, so a committee categorizing
     * item_phase_map for FestRegionRoundMigrationService is working from actual numbers
     * instead of guessing which items matter. Grouped by item_code across children, since
     * the same catalog item exists as one independent row per region today.
     *
     * @param  Collection<int, FestEvent>  $children
     */
    private function itemBreakdown(Collection $children): array
    {
        $legacyChildren = $children->filter(fn (FestEvent $c) => $c->source_phase_id === null && $c->partition_role !== null);

        $rows = collect();
        foreach ($legacyChildren as $child) {
            $items = FestEventItem::where('event_id', $child->id)->orderBy('item_code')->get();
            foreach ($items as $item) {
                $registrationIds = FestRegistration::where('item_id', $item->id)->pluck('id');
                $rows->push([
                    'region' => $child->region_id,
                    'child_event_id' => $child->id,
                    'child_title' => $child->title,
                    'item_id' => $item->id,
                    'item_code' => $item->item_code,
                    'title' => $item->title,
                    'category' => $item->category,
                    // class_group (Category 1/2/3…, i.e. the age/class band) and gender are
                    // real eligibility dimensions, not display noise — the same item title
                    // legitimately repeats once per class_group and/or once per gender, so
                    // without these two a same-titled row looks like a duplicate when it may
                    // not be one at all.
                    'class_group' => $item->class_group,
                    'gender' => $item->gender,
                    'stage_type' => $item->stage_type,
                    'registrations' => $registrationIds->count(),
                    'participants' => FestParticipant::whereIn('registration_id', $registrationIds)->count(),
                    'chest_numbers_issued' => FestParticipant::whereIn('registration_id', $registrationIds)->whereNotNull('chest_no')->count(),
                ]);
            }
        }

        // Grouped by title first (then code/region) so every row sharing a title sits
        // together — the fastest way to eyeball "different class_group/gender, so a real
        // separate item" versus "everything matches, so an actual duplicate."
        return $rows->sortBy([['title', 'asc'], ['item_code', 'asc'], ['region', 'asc']])->values()->all();
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

        if ($report['items'] !== null) {
            $this->newLine();
            $this->info(count($report['items']).' item row(s) across legacy region children — use item_code + these counts to build item_phase_map:');
            $this->table(
                ['Item code', 'Title', 'Genre', 'Class group', 'Gender', 'Stage', 'Region event', 'Regs', 'Participants', 'Chest #s issued'],
                collect($report['items'])->map(fn ($i) => [
                    $i['item_code'], $i['title'], $i['category'] ?? '—', $i['class_group'] ?? '—', $i['gender'] ?? '—', $i['stage_type'] ?? '—',
                    $i['child_event_id'], $i['registrations'], $i['participants'], $i['chest_numbers_issued'],
                ])->all()
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
