<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Services\Events\FestCertificateService;
use Illuminate\Console\Command;

class DedupeParticipationCertificates extends Command
{
    protected $signature = 'fest:participation-duplicates {event : Event id (any event of the family)} {--tenant= : Sahodaya tenant id to run against (per-Sahodaya databases)} {--fix : Remove the extra certificates (deletes the extra certificates, keeping the lowest-anchored one per student)}';

    protected $description = 'List (and with --fix remove) participation certificates that duplicate a student who already has one';

    public function handle(FestCertificateService $service): int
    {
        if ($tenant = $this->option('tenant')) {
            tenancy()->initialize($tenant);
        }

        $event = FestEvent::find($this->argument('event'));
        if (! $event) {
            $this->error('Event not found.');

            return self::FAILURE;
        }
        $root = $event->rootEvent();

        $all = \App\Models\Certificate::where('entity_type', FestParticipant::class)
            ->where('cert_type', 'participation')
            ->whereIn('entity_id', FestParticipant::where(function ($q) use ($root) {
                $q->whereIn('event_id', $root->reportableEventIds())
                    ->orWhereHas('registration', fn ($r) => $r->whereIn('event_id', $root->reportableEventIds()));
            })->pluck('id'))
            ->get();

        [, $duplicates] = $service->splitParticipationDuplicates($all);
        $this->info("Participation certificates: {$all->count()} | duplicates of a student who already has one: {$duplicates->count()}");

        if ($duplicates->isNotEmpty()) {
            $participants = FestParticipant::withoutGlobalScopes()->with(['student' => fn ($q) => $q->withTrashed()])
                ->whereIn('id', $duplicates->pluck('entity_id'))->get()->keyBy('id');
            $this->table(['cert id', 'participant id', 'student id', 'name', 'event id', 'created'], $duplicates->take(50)->map(fn ($c) => [
                $c->id,
                $c->entity_id,
                $participants[$c->entity_id]->student_id ?? '-',
                $participants[$c->entity_id]->student->name ?? '-',
                $participants[$c->entity_id]->event_id ?? '-',
                $c->created_at,
            ])->all());
        }

        $printedDuplicates = \App\Models\FestCertificatePrint::whereIn('certificate_id', $duplicates->pluck('id'))->count();
        if ($printedDuplicates > 0) {
            $this->warn("{$printedDuplicates} of these duplicates were already recorded as printed by a 'Print complete students' run -- their student's kept certificate would show as not printed. Not touching anything; tell the developer.");

            return self::FAILURE;
        }

        if ($this->option('fix') && $duplicates->isNotEmpty()) {
            // The certificate a student keeps is the one on their lowest participant row -- the
            // same one every list, print and ZIP already shows. Remove the others directly
            // (and their rendered files) instead of relying on a regeneration run.
            $removed = $service->deleteDuplicateCertificates($duplicates);
            $this->info("Removed {$removed} duplicate certificate(s).");

            $remaining = \App\Models\Certificate::where('entity_type', FestParticipant::class)
                ->where('cert_type', 'participation')
                ->whereIn('entity_id', $all->pluck('entity_id'))
                ->get();
            [, $left] = $service->splitParticipationDuplicates($remaining);
            $this->info("Participation certificates now: {$remaining->count()} | duplicates left: {$left->count()}");

            return $left->isEmpty() ? self::SUCCESS : self::FAILURE;
        } elseif ($duplicates->isNotEmpty()) {
            $this->line('Re-run with --fix to remove them.');
        }

        return self::SUCCESS;
    }
}
