<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestCompetitionArea;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Services\Events\FestItemWindowResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestCompetitionAreaWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('fest_competition_areas')) {
            $this->markTestSkipped('fest_competition_areas not migrated.');
        }
    }

    public function test_area_registration_window_closes_item(): void
    {
        $event = FestEvent::create([
            'tenant_id' => (string) Str::uuid(),
            'title' => 'STEM Fair',
            'event_type' => 'custom',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
            'registration_open' => now()->subWeek()->toDateString(),
            'registration_close' => now()->addWeek()->toDateString(),
        ]);

        $area = FestCompetitionArea::create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'name' => 'Coding',
            'slug' => 'coding',
            'sort_order' => 1,
            'is_active' => true,
            'reg_start' => now()->subMonth()->toDateString(),
            'reg_end' => now()->subDay()->toDateString(),
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'area_id' => $area->id,
            'title' => 'Scratch',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);
        $item->setRelation('event', $event);
        $item->setRelation('area', $area);

        $this->assertFalse(app(FestItemWindowResolver::class)->isRegistrationOpen($item));
    }
}
