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

        $navigation = app(\App\Services\Events\FestHeadItemNavigationService::class)->navigationForEvent($event);
        $navItem = collect($navigation['headItemGroups'])->flatMap(fn ($group) => $group['items'])->firstWhere('id', $item->id);
        $this->assertSame('Junior Athletes — Under 14', $navItem['category_label']);
        $limits = new \App\Services\Events\FestParticipationLimitService($event);
        $this->assertSame('Junior Athletes — Under 14', $limits->itemFilterOptions()[0]['category_label']);
        $this->assertSame('Junior Athletes — Under 14', \App\Services\Events\FestParticipationLimitService::categoryFilterOptions($event)['age:u14']);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        \App\Models\SahodayaProfile::create(['tenant_id' => $owner->id, 'prefix' => 'SL', 'student_data_mode' => 'counts_only']);
        $admin = \App\Models\User::factory()->create(['tenant_id' => $owner->id]);
        $admin->assignRole('sahodaya_admin');
        $base = "/sahodaya-admin/{$owner->id}/events/{$event->id}";
        $this->actingAs($admin)->get($base.'/attendance?item_id='.$item->id)->assertOk()
            ->assertInertia(fn ($page) => $page->where('event.items.0.category_label', 'Junior Athletes — Under 14'));
        $this->get($base.'/marks?item_id='.$item->id)->assertOk()
            ->assertInertia(fn ($page) => $page->where('headItemGroups', fn ($groups) => collect($groups)->flatMap(fn ($group) => $group['items'])
                ->firstWhere('id', $item->id)['category_label'] === 'Junior Athletes — Under 14'));

        $reports = new \App\Services\Events\FestReportService($event);
        $attendanceHtml = $reports->export('attendance-sheet', \Illuminate\Http\Request::create('/', 'GET', ['item_id' => $item->id, 'preview' => 1]))->getContent();
        $this->assertStringContainsString('Junior Athletes — Under 14', $attendanceHtml);
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()->andReturnUsing(function ($view, $data) {
            $this->assertStringContainsString('Junior Athletes — Under 14', view($view, $data)->render());
            $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
            $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
            $pdf->shouldReceive('download')->once()->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));

            return $pdf;
        });
        $this->get($base.'/reports/mark-entry-sheet?item_id='.$item->id)->assertOk();

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
