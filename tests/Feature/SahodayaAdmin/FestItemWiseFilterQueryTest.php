<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestEventReportAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * itemWiseReportRows() now applies the item and category filters in SQL (so a filtered
 * download hydrates only what it needs) -- the result must be exactly what the old
 * filter-after-hydration produced.
 */
class FestItemWiseFilterQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sql_filters_return_exactly_the_rows_a_php_filter_over_everything_would(): void
    {
        $sahodaya = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Filter Sahodaya', 'domain' => 'filter-q.test', 'is_active' => true]);
        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Filter School', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '5']);
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Filter Kalotsav', 'event_type' => 'kalolsavam', 'status' => 'published']);

        $spec = [['lp', 'Lower Item'], ['up', 'Upper Item A'], ['up', 'Upper Item B'], [null, 'Open Item']];
        $itemIds = [];
        foreach ($spec as $i => [$group, $title]) {
            $item = FestEventItem::create(['event_id' => $event->id, 'title' => $title, 'participant_type' => 'individual', 'is_enabled' => true, 'class_group' => $group]);
            $itemIds[$title] = $item->id;
            for ($k = 0; $k < 2; $k++) {
                $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => "S{$i}{$k}", 'status' => 'active']);
                $reg = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
                FestParticipant::create(['registration_id' => $reg->id, 'student_id' => $student->id, 'participant_type' => 'student', 'participant_role' => 'performer', 'chest_no' => $i * 10 + $k + 1]);
            }
        }

        $service = new FestEventReportAnalyticsService($event);
        $all = collect($service->itemWiseReportRows());
        $this->assertCount(8, $all);

        foreach (['lp', 'up', 'open'] as $category) {
            $expected = $all->where('category', $category)->pluck('id')->sort()->values()->all();
            $actual = collect($service->itemWiseReportRows(category: $category))->pluck('id')->sort()->values()->all();
            $this->assertSame($expected, $actual, "category {$category}");
            $this->assertNotEmpty($actual, "category {$category} should not be empty in this fixture");
        }

        $expected = $all->where('item_id', $itemIds['Upper Item B'])->pluck('id')->sort()->values()->all();
        $actual = collect($service->itemWiseReportRows(itemId: $itemIds['Upper Item B']))->pluck('id')->sort()->values()->all();
        $this->assertSame($expected, $actual);
        $this->assertCount(2, $actual);

        $this->assertSame([], $service->itemWiseReportRows(category: 'does-not-exist'));

        // Standby entrants are left out of the report entirely.
        $standbyParticipant = FestParticipant::where('registration_id', FestRegistration::where('item_id', $itemIds['Lower Item'])->first()->id)->first();
        $standbyParticipant->update(['participant_role' => 'standby']);
        $this->assertCount(7, $service->itemWiseReportRows());
        $this->assertNotContains($standbyParticipant->id, collect($service->itemWiseReportRows())->pluck('id')->all());

        // The SQL really is filtering: an item-scoped call hydrates only that item's rows.
        DB::enableQueryLog();
        $service->itemWiseReportRows(itemId: $itemIds['Lower Item']);
        $participantQuery = collect(DB::getQueryLog())->first(fn ($q) => str_contains($q['query'], 'from "fest_participants"'));
        $this->assertStringContainsString('"item_id"', $participantQuery['query']);
    }
}
