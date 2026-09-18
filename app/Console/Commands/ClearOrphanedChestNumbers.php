<?php

namespace App\Console\Commands;

use App\Models\FestParticipant;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Repairs a real production bug: rejecting or withdrawing a registration never
 * cleared its participant's chest_no (only cancel() did, and only recently — see
 * FestRegistrationReviewController::reject() and FestRegistrationBulkService::
 * rejectMany()). A participant on a rejected/withdrawn registration could still
 * carry a chest_no, invisible on the admin Chest Numbers list (which filters those
 * statuses out) but still enforced by fest_participants_event_head_chest_unique —
 * so the number looked free on screen while a fresh assignment attempt crashed on
 * the raw DB constraint (or, after that crash was separately fixed to a friendly
 * message, just stayed permanently unusable).
 *
 * Code paths going forward already clear chest_no on reject/withdraw; this is the
 * one-time repair for orphans left behind by that gap before the fix shipped.
 * Idempotent — re-running finds nothing once every tenant has been cleaned.
 */
class ClearOrphanedChestNumbers extends Command
{
    protected $signature = 'fest:clear-orphaned-chest-numbers
        {--sahodaya= : Sahodaya tenant id or subdomain}
        {--event= : Limit to one fest_events id}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Clear chest_no left behind on rejected/withdrawn registration participants';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
        }

        $tenants = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOpt, function ($q) use ($sahodayaOpt) {
                $q->where(fn ($inner) => $inner->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt));
            })
            ->orderBy('name')
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching Sahodaya tenants.');

            return self::FAILURE;
        }

        $totalCleared = 0;

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($tenant, $eventOpt, $dryRun, &$totalCleared) {
                    $cleared = $this->clearTenantDb($tenant, $eventOpt, $dryRun);
                    $totalCleared += $cleared;
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$tenant->name}: {$e->getMessage()}");
            } finally {
                // A failed initialize() can leave tenancy dangling on a broken tenant DB.
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        $this->newLine();
        $verb = $dryRun ? 'Would clear' : 'Cleared';
        $this->info("{$verb} {$totalCleared} orphaned chest number(s) total.");

        return self::SUCCESS;
    }

    private function clearTenantDb(Tenant $tenant, ?string $eventOpt, bool $dryRun): int
    {
        $orphans = FestParticipant::query()
            ->whereNotNull('chest_no')
            ->whereHas('registration', function ($q) use ($eventOpt) {
                $q->whereIn('status', ['rejected', 'withdrawn']);
                if ($eventOpt) {
                    $q->where('event_id', $eventOpt);
                }
            })
            ->with(['registration.item', 'registration.school', 'registration.event', 'student', 'teacher'])
            ->get();

        if ($orphans->isEmpty()) {
            return 0;
        }

        $this->info("Sahodaya: {$tenant->name} ({$tenant->id}) — {$orphans->count()} orphaned row(s)");
        foreach ($orphans as $p) {
            $name = $p->student?->name ?? $p->teacher?->name ?? "participant #{$p->id}";
            $item = $p->registration?->item?->title ?? 'unknown item';
            $event = $p->registration?->event?->title ?? 'unknown event';
            $school = $p->registration?->school?->name ?? 'unknown school';
            $status = $p->registration?->status;
            $this->line("  - #{$p->id} chest_no={$p->chest_no} chest_head_id={$p->chest_head_id} | {$name} ({$school}) | {$event} — {$item} | status: {$status}");
        }

        if (! $dryRun) {
            FestParticipant::whereIn('id', $orphans->pluck('id'))->update(['chest_no' => null]);
        }

        return $orphans->count();
    }
}
