<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGradeConfig;
use App\Models\Tenant;
use App\Services\Events\FestGradePointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the admin-configurable FestGradeConfig path of resolveGradeFromScore() — the one
 * branch that never got the sort-before-match fix already applied to the mcs_kalotsav/
 * confed_kalotsav presets (see FestGradePointServiceTest's docblock for that history), and
 * the new percentage-based matching (score/item.total_marks*100) added alongside the fix.
 */
class FestGradePointServiceGenericConfigTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FestGradePointService
    {
        return app(FestGradePointService::class);
    }

    private function makeEvent(): FestEvent
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Grade Config Test Sahodaya',
            'domain'    => 'grade-config-test.test',
            'is_active' => true,
        ]);

        return FestEvent::create([
            'tenant_id'   => $sahodaya->id,
            'title'       => 'Custom Scoring Fest',
            'event_type'  => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status'      => 'registration_open',
        ]);
    }

    public function test_raw_score_bands_resolve_highest_match_regardless_of_storage_order(): void
    {
        $event = $this->makeEvent();

        // Deliberately created lowest-first, mirroring the exact bug: without sorting,
        // whichever band the loop reaches LAST that the score still clears wins.
        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => null, 'grade' => 'C', 'min_score' => 50, 'max_score' => 100]);
        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => null, 'grade' => 'B', 'min_score' => 60, 'max_score' => 100]);
        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => null, 'grade' => 'A', 'min_score' => 70, 'max_score' => 100]);

        $service = $this->service();

        $this->assertSame('A', $service->resolveGradeFromScore($event, null, 85));
        $this->assertSame('A', $service->resolveGradeFromScore($event, null, 70));
        $this->assertSame('B', $service->resolveGradeFromScore($event, null, 65));
        $this->assertSame('C', $service->resolveGradeFromScore($event, null, 55));
        $this->assertNull($service->resolveGradeFromScore($event, null, 40));
    }

    public function test_item_specific_band_takes_priority_over_event_wide(): void
    {
        $event = $this->makeEvent();
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual']);

        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => null, 'grade' => 'C', 'min_score' => 0, 'max_score' => 100]);
        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => $item->id, 'grade' => 'A_plus', 'min_score' => 90, 'max_score' => 100]);

        $service = $this->service();

        // The item-specific band wins for this item when the score is in its range...
        $this->assertSame('A+', $service->resolveGradeFromScore($event, $item->id, 95));
        // ...and falls through to the event-wide band when the score misses every
        // item-specific band (item-specific is checked first, event-wide is the fallback
        // tier — not a hard override that blocks event-wide bands entirely).
        $this->assertSame('C', $service->resolveGradeFromScore($event, $item->id, 50));
        // A different item with no item-specific band at all still gets the event-wide one.
        $this->assertSame('C', $service->resolveGradeFromScore($event, null, 50));
    }

    public function test_percentage_bands_resolve_from_item_total_marks(): void
    {
        $event = $this->makeEvent();
        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Essay Writing', 'participant_type' => 'individual', 'total_marks' => 50,
        ]);

        // Stored lowest-first again, same order-independence requirement as the raw-score test.
        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => $item->id, 'grade' => 'C', 'min_percent' => 50, 'max_percent' => 100]);
        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => $item->id, 'grade' => 'B', 'min_percent' => 60, 'max_percent' => 100]);
        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => $item->id, 'grade' => 'A', 'min_percent' => 70, 'max_percent' => 100]);

        $service = $this->service();

        // 40/50 = 80% -> A, even though 40 as a raw score would only clear the (unused) C band.
        $this->assertSame('A', $service->resolveGradeFromScore($event, $item->id, 40));
        // 25/50 = 50% -> C.
        $this->assertSame('C', $service->resolveGradeFromScore($event, $item->id, 25));
        // 10/50 = 20% -> below every band.
        $this->assertNull($service->resolveGradeFromScore($event, $item->id, 10));
    }

    public function test_item_without_total_marks_ignores_percentage_bands_and_uses_raw_score(): void
    {
        $event = $this->makeEvent();
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Quiz', 'participant_type' => 'individual']);

        // A percentage band on this item would be unreachable (no total_marks to compute
        // against) — the raw-score band is what actually resolves.
        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => $item->id, 'grade' => 'A', 'min_percent' => 70, 'max_percent' => 100]);
        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => $item->id, 'grade' => 'B', 'min_score' => 40, 'max_score' => 100]);

        $service = $this->service();

        $this->assertSame('B', $service->resolveGradeFromScore($event, $item->id, 45));
    }
}
