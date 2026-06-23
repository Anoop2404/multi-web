<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestResult;
use App\Models\KalotsavCategory;
use App\Models\KalotsavEvent;
use App\Models\KalotsavResult;
use Illuminate\Console\Command;

class MigrateKalotsavToFest extends Command
{
    protected $signature = 'fest:migrate-kalotsav {tenantId : Sahodaya tenant id} {--dry-run : Preview without writing}';

    protected $description = 'Migrate legacy kalotsav_* CMS tables into fest_* operational tables';

    public function handle(): int
    {
        $tenantId = $this->argument('tenantId');
        $dryRun = $this->option('dry-run');

        $legacyEvents = KalotsavEvent::where('tenant_id', $tenantId)->with('categories.results')->get();

        if ($legacyEvents->isEmpty()) {
            $this->info('No legacy kalotsav events found.');

            return self::SUCCESS;
        }

        foreach ($legacyEvents as $legacy) {
            $this->line("Migrating: {$legacy->name}");

            if ($dryRun) {
                continue;
            }

            $fest = FestEvent::create([
                'tenant_id'         => $tenantId,
                'title'             => $legacy->name,
                'event_type'        => 'kalolsavam',
                'conductor_level'   => 'sahodaya',
                'event_start'       => $legacy->event_date,
                'venue'             => $legacy->venue,
                'status'            => $legacy->is_active ? 'completed' : 'draft',
                'results_published' => (bool) $legacy->results_published,
                'description'       => $legacy->description,
            ]);

            foreach ($legacy->categories as $cat) {
                FestEventItem::create([
                    'event_id'      => $fest->id,
                    'title'         => $cat->name,
                    'category'      => 'general',
                    'display_order' => $cat->display_order ?? 0,
                ]);

                foreach ($cat->results as $result) {
                    FestResult::updateOrCreate(
                        [
                            'event_id'  => $fest->id,
                            'item_id'   => null,
                            'school_id' => $result->school_tenant_id,
                        ],
                        [
                            'total_points' => (int) ($result->points ?? 0),
                            'rank'         => is_numeric($result->position) ? (int) $result->position : null,
                            'published_at' => $legacy->results_published ? now() : null,
                        ]
                    );
                }
            }
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Migration complete.');

        return self::SUCCESS;
    }
}
