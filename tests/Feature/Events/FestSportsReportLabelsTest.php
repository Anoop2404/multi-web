<?php

namespace Tests\Feature\Events;

use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSportsAgeGroupConfig;
use App\Models\FestTrophy;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestEventReportAnalyticsService;
use App\Services\Events\FestSchoolReportAnalyticsService;
use App\Services\Events\FestTrophyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestSportsReportLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sports_reports_preserve_custom_category_labels(): void
    {
        $owner = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Sports Owner', 'is_active' => true]);
        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $owner->id, 'name' => 'Sports School', 'is_active' => true]);
        FestSportsAgeGroupConfig::create(['tenant_id' => $owner->id, 'group_key' => 'u14', 'label' => 'Junior Athletes — Under 14', 'under_age' => 14, 'is_active' => true]);
        $event = FestEvent::create(['tenant_id' => $owner->id, 'title' => 'Sports Meet', 'event_type' => 'sports', 'level_round' => 'sahodaya', 'status' => 'ongoing']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => '100 metres', 'age_group' => 'u14', 'class_group' => 'lp', 'is_enabled' => true]);
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class 7']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Sports Student']);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['event_id' => $event->id, 'registration_id' => $registration->id, 'student_id' => $student->id, 'participant_type' => 'student', 'participant_role' => 'performer']);
        FestAttendance::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'status' => 'absent', 'marked_at' => now()]);

        $status = (new FestSchoolReportAnalyticsService($event, $school->id))->resultsPublishStatus();
        $this->assertSame('Junior Athletes — Under 14', $status['rows'][0]['category_label']);
        $this->assertSame('u14', $status['rows'][0]['age_group']);
        $absent = (new FestEventReportAnalyticsService($event))->absentReportRows($school->id);
        $this->assertSame('Junior Athletes — Under 14', $absent[0]['category_label']);
        $trophies = app(FestTrophyService::class)->resolveWinners($event, collect([
            new FestTrophy(['trophy_type' => FestTrophy::TYPE_ITEM, 'item_id' => $item->id, 'category_key' => 'u14', 'position' => 1]),
        ]));
        $this->assertSame('Junior Athletes — Under 14', $trophies->first()['trophy']['category_label']);
    }
}
