<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Services\Events\FestMarkCriteriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestMarkCriteriaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_copy_criteria_from_item_replaces_target_item_criteria_and_judge_count(): void
    {
        $event = FestEvent::create([
            'tenant_id'   => (string) Str::uuid(),
            'title'       => 'Copy Criteria Kalotsav',
            'event_type'  => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status'      => 'registration_open',
        ]);

        $source = FestEventItem::create([
            'event_id'         => $event->id,
            'title'            => 'On Stage Item A',
            'participant_type' => 'individual',
            'is_enabled'       => true,
        ]);

        $target = FestEventItem::create([
            'event_id'         => $event->id,
            'title'            => 'On Stage Item B',
            'participant_type' => 'individual',
            'is_enabled'       => true,
        ]);

        $service = app(FestMarkCriteriaService::class);

        $service->saveCriteria($event, $source, [
            ['label' => 'Content', 'max_score' => 10],
            ['label' => 'Presentation', 'max_score' => 15],
        ]);
        $service->setJudgeCount($source, 3);

        // Target starts with an unrelated column — the copy must fully replace it, not merge.
        $service->saveCriteria($event, $target, [
            ['label' => 'Old stale column', 'max_score' => 5],
        ]);

        $service->copyCriteriaFromItem($event, $source, $target);

        $targetCriteria = $service->criteriaForItem($target)->values();

        $this->assertSame(['Content', 'Presentation'], $targetCriteria->pluck('label')->all());
        $this->assertSame([10.0, 15.0], $targetCriteria->pluck('max_score')->map(fn ($v) => (float) $v)->all());
        $this->assertSame(3, $service->judgeCountForItem($target));

        // Source is untouched by copying from it.
        $sourceCriteria = $service->criteriaForItem($source)->values();
        $this->assertSame(['Content', 'Presentation'], $sourceCriteria->pluck('label')->all());
        $this->assertSame(3, $service->judgeCountForItem($source));
    }
}
