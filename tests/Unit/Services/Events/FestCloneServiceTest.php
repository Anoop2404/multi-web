<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Services\Events\FestCloneService;
use App\Services\Events\FestMarkCriteriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestCloneServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_clone_event_carries_over_mark_entry_criteria_and_judge_count(): void
    {
        $source = FestEvent::create([
            'tenant_id' => (string) Str::uuid(),
            'title' => 'Source Kalotsav',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $item = FestEventItem::create([
            'event_id' => $source->id,
            'title' => 'On Stage Item',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);

        $criteriaService = app(FestMarkCriteriaService::class);
        $criteriaService->saveCriteria($source, $item, [
            ['label' => 'Content', 'max_score' => 10],
            ['label' => 'Presentation', 'max_score' => 15],
        ]);
        $criteriaService->setJudgeCount($item, 3);

        $clone = app(FestCloneService::class)->cloneEvent($source, 'Cloned Kalotsav');

        $this->assertCount(1, $clone->items);
        $clonedItem = $clone->items->first();

        $this->assertNotSame($item->id, $clonedItem->id);
        $this->assertSame(3, $criteriaService->judgeCountForItem($clonedItem));

        $clonedCriteria = $criteriaService->criteriaForItem($clonedItem)->values();
        $this->assertSame(['Content', 'Presentation'], $clonedCriteria->pluck('label')->all());
        $this->assertSame([10.0, 15.0], $clonedCriteria->pluck('max_score')->map(fn ($v) => (float) $v)->all());

        // Source item's own criteria are untouched by cloning.
        $this->assertCount(2, $criteriaService->criteriaForItem($item));
    }
}
