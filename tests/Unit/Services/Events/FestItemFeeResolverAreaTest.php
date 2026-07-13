<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestCompetitionArea;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Services\Events\FestItemFeeResolver;
use Tests\TestCase;

class FestItemFeeResolverAreaTest extends TestCase
{
    public function test_area_default_fee_applies_when_item_fee_blank(): void
    {
        $event = new FestEvent(['event_type' => 'custom']);
        $area = new FestCompetitionArea(['default_item_fee' => 150]);
        $item = new FestEventItem(['area_id' => 1, 'fee_amount' => null, 'participant_type' => 'individual']);
        $item->setRelation('area', $area);

        $amount = app(FestItemFeeResolver::class)->amountForItem($item, [], $event);

        $this->assertSame(150.0, $amount);
    }

    public function test_item_fee_overrides_area_fee(): void
    {
        $event = new FestEvent(['event_type' => 'custom']);
        $area = new FestCompetitionArea(['default_item_fee' => 150]);
        $item = new FestEventItem(['area_id' => 1, 'fee_amount' => 75, 'participant_type' => 'individual']);
        $item->setRelation('area', $area);

        $amount = app(FestItemFeeResolver::class)->amountForItem($item, [], $event);

        $this->assertSame(75.0, $amount);
    }

    public function test_sports_composite_ignores_area_fee_path(): void
    {
        $event = new FestEvent(['event_type' => 'sports']);
        $area = new FestCompetitionArea(['default_item_fee' => 999]);
        $item = new FestEventItem(['area_id' => 1, 'fee_amount' => null, 'participant_type' => 'individual']);
        $item->setRelation('area', $area);

        $amount = app(FestItemFeeResolver::class)->amountForItem(
            $item,
            ['fee_model' => 'sports_composite', 'default_item_fee' => 40],
            $event
        );

        $this->assertSame(40.0, $amount);
    }
}
