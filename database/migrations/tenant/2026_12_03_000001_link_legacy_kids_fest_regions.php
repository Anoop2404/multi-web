<?php

use App\Models\FestEvent;
use App\Services\Events\FestKidsFestRegionLinkService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        FestEvent::where('event_type', 'kids_fest')
            ->whereNotNull('parent_event_id')
            ->whereNull('source_phase_id')
            ->whereNull('region_id')
            ->whereIn('partition_role', ['region', 'cluster'])
            ->chunkById(100, function ($partitions) {
                foreach ($partitions as $partition) {
                    app(FestKidsFestRegionLinkService::class)->ensureRegionLink($partition);
                }
            });
    }

    public function down(): void
    {
        // Preserve region identities: requests may reference them after migration.
    }
};
