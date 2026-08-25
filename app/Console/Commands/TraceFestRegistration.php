<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Services\Events\FestSchoolPhaseRegionService;
use Illuminate\Console\Command;

/**
 * Read-only diagnostic for "registration showed success but the item never
 * flips to REGISTERED": traces where a student's registration for a given
 * item actually landed (which event_id), versus where the school-facing page
 * is looking (the event_id in the URL) — and shows what the phase/region
 * router would resolve for that item+school today, to catch a silent
 * write/read mismatch caused by FestRegistrationRouterService.
 */
class TraceFestRegistration extends Command
{
    protected $signature = 'fest:trace-registration
        {item : fest_event_items id the school registered for}
        {student : students id the registration is for}
        {--sahodaya= : Sahodaya tenant id or subdomain}
        {--school= : school tenant id (uuid) — narrows the "any event" search}';

    protected $description = 'Trace a student+item fest registration across all events under its hub to diagnose a success-toast-but-not-registered mismatch';

    public function handle(): int
    {
        $itemId = (int) $this->argument('item');
        $studentId = (int) $this->argument('student');
        $sahodayaOpt = $this->option('sahodaya');
        $schoolId = $this->option('school');

        $tenants = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOpt, function ($q) use ($sahodayaOpt) {
                $q->where(function ($inner) use ($sahodayaOpt) {
                    $inner->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
                });
            })
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching Sahodaya tenant(s). Pass --sahodaya to narrow the search.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($itemId, $studentId, $schoolId, $tenant) {
                    $item = FestEventItem::find($itemId);
                    if (! $item) {
                        $this->line("Sahodaya {$tenant->name} ({$tenant->id}): no fest_event_items row with id={$itemId}.");

                        return;
                    }

                    $this->info("Sahodaya: {$tenant->name} ({$tenant->id})");
                    $this->line("Item #{$item->id} \"{$item->title}\" (code {$item->item_code}) belongs to event_id={$item->event_id}, phase_id=".($item->phase_id ?? 'NULL'));

                    // 1. Everything this student is actually registered for, anywhere
                    //    in this Sahodaya — the ground truth of where the write landed.
                    $participants = FestParticipant::where('student_id', $studentId)
                        ->with(['registration.item', 'registration.event'])
                        ->get()
                        ->filter(fn ($p) => $p->registration !== null);

                    if ($participants->isEmpty()) {
                        $this->warn("No FestParticipant rows at all for student_id={$studentId} — registration never persisted anywhere, or wrong student id.");
                    } else {
                        $this->line("All registrations found for student_id={$studentId}:");
                        $this->table(
                            ['registration_id', 'event_id', 'event_title', 'item_id', 'item_code', 'item_title', 'status'],
                            $participants->map(fn ($p) => [
                                $p->registration->id,
                                $p->registration->event_id,
                                $p->registration->event->title ?? '?',
                                $p->registration->item_id,
                                $p->registration->item->item_code ?? '?',
                                $p->registration->item->title ?? '?',
                                $p->registration->status,
                            ])->all()
                        );

                        $matchOnThisItem = $participants->first(fn ($p) => $p->registration->item_id === $itemId);
                        if ($matchOnThisItem) {
                            $this->info("Found: registration_id={$matchOnThisItem->registration->id} for THIS exact item, under event_id={$matchOnThisItem->registration->event_id}.");
                        } else {
                            $sameCode = $participants->first(fn ($p) => ($p->registration->item->item_code ?? null) === $item->item_code);
                            if ($sameCode) {
                                $this->warn("No registration for item_id={$itemId} directly, but found one for the SAME item_code \"{$item->item_code}\" as a DIFFERENT item_id ({$sameCode->registration->item_id}) under event_id={$sameCode->registration->event_id}. This is the write/read mismatch — the router redirected the write to a sibling item/event.");
                            } else {
                                $this->error('No registration for this item or its item_code anywhere for this student. The write did not persist at all (silent failure), not a routing mismatch.');
                            }
                        }
                    }

                    // 2. What would the phase/region router resolve TODAY for this
                    //    item + school? (Only meaningful if --school is passed.)
                    if ($schoolId && $item->phase_id) {
                        $phase = $item->phase;
                        $sourcePhase = $phase?->sourcePhase ?: $phase;
                        $hub = $item->event->rootEvent();

                        if ($sourcePhase && $hub) {
                            try {
                                $resolved = app(FestSchoolPhaseRegionService::class)
                                    ->operationalEvent($hub, $sourcePhase, $schoolId);
                                $this->line("Router resolves item's phase (id={$sourcePhase->id} \"{$sourcePhase->name}\") + school={$schoolId} to event_id={$resolved->id} (\"{$resolved->title}\", partition_role=".($resolved->partition_role ?? 'NULL').').');
                                if ((int) $resolved->id !== $item->event_id) {
                                    $this->warn("MISMATCH: item #{$itemId} lives on event_id={$item->event_id}, but the router resolves this school to event_id={$resolved->id}. A registration submitted from event_id={$item->event_id}'s page would be silently written to event_id={$resolved->id} instead.");
                                }
                            } catch (\Throwable $e) {
                                $this->error('operationalEvent() threw: '.$e->getMessage());
                            }
                        }
                    } elseif (! $schoolId) {
                        $this->line('(Pass --school=<school-tenant-uuid> to also see what the router resolves today for this item.)');
                    }
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$tenant->name}: {$e->getMessage()}");
            } finally {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        return self::SUCCESS;
    }
}
