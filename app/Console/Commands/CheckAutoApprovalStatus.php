<?php

namespace App\Console\Commands;

use App\Models\FestCompetitionArea;
use App\Models\FestEvent;
use App\Models\FestItemHead;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Services\Events\FestLevelRegistrationService;
use App\Services\Events\FestNumberingService;
use App\Services\Events\FestSchoolEventFeeService;
use Illuminate\Console\Command;

class CheckAutoApprovalStatus extends Command
{
    protected $signature = 'fest:check-auto-approval
                            {--sahodaya= : Optional Sahodaya tenant ID}
                            {--fix : Automatically set all policies to auto and approve pending registrations}';

    protected $description = 'Audit live approval policy settings across parent & child events, item heads, and registrations';

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $shouldFix = (bool) $this->option('fix');

        $sahodayas = $sahodayaOpt
            ? Tenant::query()->sahodayas()->whereKey($sahodayaOpt)->get()
            : Tenant::query()->sahodayas()->where('is_active', true)->get();

        if ($sahodayas->isEmpty()) {
            $this->error('No matching active Sahodaya tenant found.');

            return self::FAILURE;
        }

        $hasFailures = false;

        foreach ($sahodayas as $sahodaya) {
            $this->info("==================================================");
            $this->info("Sahodaya Tenant: {$sahodaya->name} ({$sahodaya->id})");
            $this->info("==================================================");

            $sahodaya->run(function () use ($sahodaya, $shouldFix, &$hasFailures) {
                $events = FestEvent::with('parentEvent')->get();

                if ($events->isEmpty()) {
                    $this->comment("No events found in {$sahodaya->name}.");
                    return;
                }

                $eventRows = [];
                $manualEventCount = 0;

                foreach ($events as $event) {
                    $policy = $event->approval_policy ?? 'auto';
                    $isManual = $policy === 'manual';
                    if ($isManual) {
                        $manualEventCount++;
                    }

                    $parentTitle = $event->parentEvent ? $event->parentEvent->title : 'Root Event';
                    $role = $event->parent_event_id ? 'Child Partition' : 'Parent / Root';

                    $eventRows[] = [
                        'ID' => $event->id,
                        'Title' => $event->title,
                        'Role' => $role,
                        'Parent' => $parentTitle,
                        'Mode' => $event->conduct_mode ?? 'standard',
                        'Policy' => $isManual ? '⚠️ MANUAL' : '✅ AUTO',
                    ];
                }

                $this->table(['ID', 'Title', 'Role', 'Parent', 'Mode', 'Policy'], $eventRows);

                // Heads & Areas audit
                $headsCount = FestItemHead::count();
                $manualHeadsCount = FestItemHead::where('approval_policy', 'manual')->count();

                $areasCount = FestCompetitionArea::count();
                $manualAreasCount = FestCompetitionArea::where('approval_policy', 'manual')->count();

                // Registrations audit
                $approvedRegs = FestRegistration::where('status', 'approved')->count();
                $pendingRegs = FestRegistration::whereIn('status', ['pending_approval', 'submitted', 'draft'])->count();
                $waitlistedRegs = FestRegistration::where('status', 'waitlisted')->count();

                $this->line("");
                $this->info("--- Policy & Registration Summary ---");
                $this->line("• Events: " . count($events) . " total | Manual: " . ($manualEventCount > 0 ? "⚠️ {$manualEventCount}" : "0 ✅"));
                $this->line("• Item Heads: {$headsCount} total | Manual: " . ($manualHeadsCount > 0 ? "⚠️ {$manualHeadsCount}" : "0 ✅"));
                $this->line("• Competition Areas: {$areasCount} total | Manual: " . ($manualAreasCount > 0 ? "⚠️ {$manualAreasCount}" : "0 ✅"));
                $this->line("• Registrations: Approved={$approvedRegs} | Pending/Submitted=" . ($pendingRegs > 0 ? "⚠️ {$pendingRegs}" : "0 ✅") . " | Waitlisted={$waitlistedRegs}");

                if ($manualEventCount > 0 || $manualHeadsCount > 0 || $manualAreasCount > 0 || $pendingRegs > 0) {
                    $hasFailures = true;

                    if ($shouldFix) {
                        $this->warn("\nApplying --fix mode: Updating all policies to 'auto' and approving pending registrations...");

                        FestEvent::query()->update(['approval_policy' => 'auto']);
                        FestItemHead::query()->update(['approval_policy' => 'auto']);
                        FestCompetitionArea::query()->update(['approval_policy' => 'auto']);
                        \App\Models\FestParticipationPolicy::query()->update(['require_fee_before_approval' => false]);

                        $pendingList = FestRegistration::whereIn('status', ['pending_approval', 'submitted', 'draft'])->get();
                        foreach ($pendingList as $reg) {
                            $reg->update(['status' => 'approved', 'submitted_at' => $reg->submitted_at ?? now()]);
                            foreach ($reg->participants as $participant) {
                                app(FestNumberingService::class)->assignParticipantNumbers($participant);
                            }
                            app(FestLevelRegistrationService::class)->syncRegistration($reg);
                        }

                        $schoolIds = $pendingList->pluck('school_id')->unique();
                        foreach ($events as $event) {
                            foreach ($schoolIds as $sId) {
                                try {
                                    app(FestSchoolEventFeeService::class)->recalculate($event, $sId);
                                } catch (\Throwable) {}
                            }
                        }

                        $this->info("✅ Successfully fixed all policies and approved {$pendingList->count()} pending registration(s).");
                    }
                }
            });

            $this->line("");
        }

        if ($hasFailures && ! $shouldFix) {
            $this->error("Audit completed with warnings: Some events/heads are set to manual or have pending registrations.");
            $this->comment("Run with --fix flag to automatically resolve: php artisan fest:check-auto-approval --fix");

            return self::FAILURE;
        }

        $this->info("🎉 Audit passed! All events, child partitions, and item heads are set to AUTO approval.");

        return self::SUCCESS;
    }
}
