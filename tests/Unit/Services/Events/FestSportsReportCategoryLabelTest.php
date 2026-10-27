<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Services\Events\FestSportsAgeGroupRegistry;
use App\Support\FestItemCategoryLabel;
use Tests\TestCase;

class FestSportsReportCategoryLabelTest extends TestCase
{
    public function test_sports_reports_use_tenant_age_names_ahead_of_legacy_class_categories(): void
    {
        $registry = $this->mock(FestSportsAgeGroupRegistry::class);
        $registry->shouldReceive('forTenant')->with('sports-report-label-test')->andReturnSelf();
        $registry->shouldReceive('labels')->andReturn(['u14' => 'Under 14 Juniors', 'open' => 'All Ages']);
        $registry->shouldReceive('isValid')->with('u14')->andReturnTrue();
        $event = new FestEvent(['event_type' => 'sports', 'tenant_id' => 'sports-report-label-test']);
        $item = new FestEventItem(['event_id' => 1, 'age_group' => 'u14', 'class_group' => 'lp']);
        $item->setRelation('event', $event);
        $this->assertSame('Under 14 Juniors', FestItemCategoryLabel::resolve($item, ['lp' => 'Lower Primary']));
        $item->age_group = 'open';
        $this->assertSame('All Ages', FestItemCategoryLabel::resolve($item, ['lp' => 'Lower Primary']));
        $item->age_group = null;
        $this->assertSame('Under 14 Juniors', FestItemCategoryLabel::resolve($item, ['lp' => 'Lower Primary']));
        $event->event_type = 'kalolsavam';
        $this->assertSame('Lower Primary', FestItemCategoryLabel::resolve($item, ['lp' => 'Lower Primary']));
    }
}
