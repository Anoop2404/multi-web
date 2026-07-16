<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only diagnostic: dumps the raw, un-cast DB values for an event's date
 * columns plus tenancy context, to settle whether a "dates not showing on the
 * school page" report is a data problem (columns actually null/wrong tenant)
 * or a rendering/caching problem (columns are fine, page just isn't showing them).
 */
class InspectFestEventDates extends Command
{
    protected $signature = 'fest:inspect-event-dates
        {event : fest_events id to inspect}
        {--sahodaya= : Sahodaya tenant id or subdomain}';

    protected $description = 'Dump raw date/status/tenant columns for a FestEvent row, bypassing casts/scopes, to debug why a school-facing page might show different data than the admin panel';

    public function handle(): int
    {
        $eventId = (int) $this->argument('event');
        $sahodayaOpt = $this->option('sahodaya');

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
                $tenant->run(function () use ($eventId, $tenant) {
                    // Raw query — bypasses Eloquent casts/accessors entirely, so we see
                    // exactly what's in Postgres.
                    $raw = DB::table('fest_events')->where('id', $eventId)->first();

                    if (! $raw) {
                        $this->line("Sahodaya {$tenant->name} ({$tenant->id}): no fest_events row with id={$eventId}.");

                        return;
                    }

                    $this->info("Sahodaya: {$tenant->name} ({$tenant->id})");
                    $this->table(['column', 'raw value'], [
                        ['id', $raw->id],
                        ['tenant_id', $raw->tenant_id],
                        ['title', $raw->title],
                        ['event_type', $raw->event_type],
                        ['status', $raw->status],
                        ['parent_event_id', $raw->parent_event_id ?? 'NULL'],
                        ['partition_role', $raw->partition_role ?? 'NULL'],
                        ['conducting_school_id', $raw->conducting_school_id ?? 'NULL'],
                        ['event_start', $raw->event_start ?? 'NULL'],
                        ['event_end', $raw->event_end ?? 'NULL'],
                        ['registration_open', $raw->registration_open ?? 'NULL'],
                        ['registration_close', $raw->registration_close ?? 'NULL'],
                        ['event_reg_start', $raw->event_reg_start ?? 'NULL'],
                        ['event_reg_end', $raw->event_reg_end ?? 'NULL'],
                        ['updated_at', $raw->updated_at ?? 'NULL'],
                    ]);

                    // Also load it through Eloquent, exactly like the school controller
                    // does, to see if a scope/cast is silently changing anything.
                    $model = FestEvent::find($eventId);
                    if ($model) {
                        $this->line('Via Eloquent (App\Models\FestEvent::find):');
                        $this->table(['column', 'cast value'], [
                            ['event_start', (string) ($model->event_start ?? 'NULL')],
                            ['event_end', (string) ($model->event_end ?? 'NULL')],
                            ['registration_open', (string) ($model->registration_open ?? 'NULL')],
                            ['registration_close', (string) ($model->registration_close ?? 'NULL')],
                        ]);
                    }

                    // How many rows share this id across duplicate-detection angles.
                    $sameTitleCount = DB::table('fest_events')
                        ->where('title', $raw->title)
                        ->where('event_type', $raw->event_type)
                        ->count();
                    if ($sameTitleCount > 1) {
                        $this->warn("Note: {$sameTitleCount} fest_events rows share title \"{$raw->title}\" + event_type \"{$raw->event_type}\" in this Sahodaya — possible duplicate.");
                        $dupes = DB::table('fest_events')
                            ->where('title', $raw->title)
                            ->where('event_type', $raw->event_type)
                            ->get(['id', 'status', 'event_start', 'registration_open', 'parent_event_id']);
                        $this->table(['id', 'status', 'event_start', 'registration_open', 'parent_event_id'], $dupes->map(fn ($d) => [
                            $d->id, $d->status, $d->event_start ?? 'NULL', $d->registration_open ?? 'NULL', $d->parent_event_id ?? 'NULL',
                        ])->all());
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
