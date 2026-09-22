<?php

namespace App\Console\Commands;

use App\Jobs\RenderContinuousDieIdCardsJob;
use App\Models\FestEvent;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Events\FestIdCardService;
use Illuminate\Console\Command;

class RenderContinuousDieIdCardsCommand extends Command
{
    protected $signature = 'id-cards:render-continuous-die
                            {--sahodaya= : Sahodaya tenant UUID, subdomain, or custom domain}
                            {--event= : FestEvent ID}
                            {--queue : Dispatch to queue worker instead of running synchronously}';

    protected $description = 'Render the continuous master ID card die sheet with zero wasted slots and save directly to AWS S3';

    public function handle(FestIdCardService $service): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventIdOpt = $this->option('event');
        $useQueue = (bool) $this->option('queue');

        $tenant = $sahodayaOpt
            ? Tenant::query()->sahodayas()->where(function ($q) use ($sahodayaOpt) {
                $q->where('id', $sahodayaOpt)
                    ->orWhere('subdomain', $sahodayaOpt)
                    ->orWhere('domain', $sahodayaOpt);
            })->first()
            : Tenant::query()->sahodayas()->where('is_active', true)->first();

        if (! $tenant) {
            $this->error("Sahodaya tenant [{$sahodayaOpt}] not found.");

            return self::FAILURE;
        }

        $event = $tenant->run(function () use ($eventIdOpt) {
            return $eventIdOpt
                ? FestEvent::find($eventIdOpt)
                : FestEvent::where('status', '!=', 'cancelled')->latest('id')->first();
        });

        if (! $event) {
            $this->error("Fest event [{$eventIdOpt}] not found for Sahodaya {$tenant->name}.");

            return self::FAILURE;
        }

        $this->info("── Preparing Continuous Die ID Cards Render ──");
        $this->line("Sahodaya: {$tenant->name} ({$tenant->id})");
        $this->line("Event:    {$event->title} (#{$event->id})");

        if ($useQueue) {
            TenantSetting::updateOrCreate(
                ['tenant_id' => $tenant->id, 'key' => "fest_die_render_event_{$event->id}"],
                ['value' => [
                    'status' => 'queued',
                    'queued_at' => now()->toIso8601String(),
                    'error' => null,
                ]]
            );

            RenderContinuousDieIdCardsJob::dispatch($tenant->id, $event->id);
            $this->info("✓ Job dispatched to queue successfully.");

            return self::SUCCESS;
        }

        $this->info("Rendering synchronously in CLI (this may take 1-3 minutes for 1,000+ cards)...");
        $job = new RenderContinuousDieIdCardsJob($tenant->id, $event->id);
        $job->handle($service);

        $state = $tenant->run(function () use ($tenant, $event) {
            return TenantSetting::where('tenant_id', $tenant->id)
                ->where('key', "fest_die_render_event_{$event->id}")
                ->value('value');
        });

        if (($state['status'] ?? null) === 'completed') {
            $this->info("✓ Render completed successfully!");
            $this->table(['Key', 'Value'], [
                ['Total Cards', $state['total_cards'] ?? 'N/A'],
                ['Continuous Sheets', $state['total_sheets'] ?? 'N/A'],
                ['File Size', $state['file_size_formatted'] ?? 'N/A'],
                ['S3 Path', $state['file_path'] ?? 'N/A'],
                ['Rendered At', $state['rendered_at_human'] ?? 'N/A'],
            ]);

            return self::SUCCESS;
        }

        $this->error("Render failed: " . ($state['error'] ?? 'Unknown error'));

        return self::FAILURE;
    }
}
