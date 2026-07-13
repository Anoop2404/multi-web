<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Services\Events\FestQualificationService;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class FestQualificationTiebreakTest extends TestCase
{
    private function select(Collection $marks, int $limit, string $mode): Collection
    {
        $service = app(FestQualificationService::class);
        $method = new ReflectionMethod($service, 'selectMarksForPromotion');
        $method->setAccessible(true);

        $from = new FestEvent(['id' => 1]);
        $to = new FestEvent(['id' => 2]);
        $item = new FestEventItem(['id' => 10, 'title' => 'Demo']);

        return $method->invoke($service, $marks, $limit, $mode, $from, $item, $to);
    }

    private function marks(array $positions): Collection
    {
        return collect($positions)->values()->map(function ($position, $i) {
            $m = new FestMark(['position' => $position, 'participant_id' => $i + 1]);
            $m->id = $i + 1;

            return $m;
        });
    }

    public function test_none_takes_top_n_by_position(): void
    {
        $selected = $this->select($this->marks([1, 2, 2, 3]), 2, 'none');

        $this->assertCount(3, $selected);
        $this->assertTrue($selected->every(fn ($m) => $m->position <= 2));
    }

    public function test_include_all_ties_expands_cutoff(): void
    {
        // Unique ranks 1,2,3 — take 2 unique → cutoff rank 2 → both at position 2 included
        $selected = $this->select($this->marks([1, 2, 2, 3]), 2, 'include_all_ties');

        $this->assertCount(3, $selected);
        $this->assertSame([1, 2, 2], $selected->pluck('position')->all());
    }

    public function test_exclude_ties_skips_overflow_group(): void
    {
        // limit 2: take position 1, skip tied pair at 2 (would overflow), fill with next clean rank
        $selected = $this->select($this->marks([1, 2, 2, 3]), 2, 'exclude_ties');

        $this->assertCount(2, $selected);
        $this->assertSame([1, 3], $selected->pluck('position')->all());
    }

    public function test_manual_aborts(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->select($this->marks([1, 2]), 1, 'manual');
    }
}
