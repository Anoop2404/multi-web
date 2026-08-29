<?php

namespace Tests\Unit\Services\Events;

use App\Models\ClassCategory;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestEventReportAnalyticsService;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Student-wise report's rank/mark/grade must stay hidden until an item's results
 * are actually published — same gate FestItemResultsService::isItemVisible() already
 * applies to the dedicated results-entry/publish pages — since this report is the one
 * a Sahodaya downloads as a PDF/Excel and may hand straight to a school.
 */
class FestStudentWiseReportResultsGatingTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodayaAndSchool(): array
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Results Gating Sahodaya',
            'domain'    => Str::slug('results-gating-sahodaya-'.Str::random(6)).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'RG'.strtoupper(Str::random(2)),
            'student_data_mode' => 'full_records',
        ]);

        $school = Tenant::create([
            'id'            => (string) Str::uuid(),
            'type'          => 'school',
            'name'          => 'Results Gating School',
            'parent_id'     => $sahodaya->id,
            'school_prefix' => 'RS'.strtoupper(Str::random(2)),
            'is_active'     => true,
        ]);

        return compact('sahodaya', 'school');
    }

    private function makeStudent(Tenant $school): Student
    {
        $class = SchoolClass::firstOrCreate(
            ['tenant_id' => $school->id, 'name' => 'Gating Test Class'],
            ['class_category_id' => ClassCategory::whereNull('sahodaya_id')->value('id'), 'is_active' => true]
        );

        return Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $class->id,
            'name'            => 'Gated Student',
            'gender'          => 'female',
            'dob'             => '2011-01-01',
            'status'          => 'active',
        ]);
    }

    public function test_rank_mark_grade_hidden_until_item_results_published(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();
        $student = $this->makeStudent($school);

        $event = FestEvent::create([
            'tenant_id'         => $sahodaya->id,
            'title'             => 'Gating Kalolsav',
            'event_type'        => 'kalolsavam',
            'level_round'       => 'sahodaya',
            'status'            => 'registration_open',
            'results_published' => false,
            'fee_settings'      => ['fee_model' => 'none'],
        ]);

        $unpublishedItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Unpublished Item',
            'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true,
        ]);
        $publishedItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Published Item',
            'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);

        foreach ([$unpublishedItem, $publishedItem] as $item) {
            $registration = FestRegistration::create([
                'event_id' => $event->id, 'item_id' => $item->id,
                'school_id' => $school->id, 'status' => 'approved',
            ]);
            $participant = FestParticipant::create([
                'registration_id' => $registration->id, 'student_id' => $student->id,
                'participant_role' => 'performer',
            ]);
            FestMark::create([
                'event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id,
                'grade' => 'A+', 'position' => 1, 'score' => 95,
            ]);
        }

        $analytics = app(FestEventReportAnalyticsService::class, ['event' => $event]);
        $rows = $analytics->studentWiseBrowserRows($school->id);

        $this->assertCount(1, $rows);
        $itemsByTitle = collect($rows[0]['items'])->keyBy('item_title');

        $unpublished = $itemsByTitle->get('Unpublished Item');
        $this->assertFalse($unpublished['results_published']);
        $this->assertNull($unpublished['grade']);
        $this->assertNull($unpublished['position']);
        $this->assertNull($unpublished['score']);

        $published = $itemsByTitle->get('Published Item');
        $this->assertTrue($published['results_published']);
        $this->assertSame('A+', $published['grade']);
        $this->assertSame(1, $published['position']);
        $this->assertEquals(95, $published['score']);

        // Photo data URI is only computed when the caller opts in (PDF generation) —
        // never on every interactive browser page load.
        $this->assertNull($rows[0]['photo_data_uri']);
    }

    public function test_event_wide_publish_flag_also_makes_results_visible(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();
        $student = $this->makeStudent($school);

        $event = FestEvent::create([
            'tenant_id'         => $sahodaya->id,
            'title'             => 'Event-Wide Publish Kalolsav',
            'event_type'        => 'kalolsavam',
            'level_round'       => 'sahodaya',
            'status'            => 'registration_open',
            'results_published' => true,
            'fee_settings'      => ['fee_model' => 'none'],
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Item Under Event Publish',
            'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true,
        ]);
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id,
            'school_id' => $school->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'student_id' => $student->id,
            'participant_role' => 'performer',
        ]);
        FestMark::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id,
            'grade' => 'B', 'position' => 3, 'score' => 70,
        ]);

        $analytics = app(FestEventReportAnalyticsService::class, ['event' => $event]);
        $rows = $analytics->studentWiseBrowserRows($school->id);

        $this->assertTrue($rows[0]['items'][0]['results_published']);
        $this->assertSame('B', $rows[0]['items'][0]['grade']);
    }

    public function test_stage_type_and_participant_type_are_carried_through_for_the_report(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();
        $student = $this->makeStudent($school);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Stage Type Kalolsav',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
            'fee_settings' => ['fee_model' => 'none'],
        ]);

        $onStageItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Mono Act',
            'participant_type' => 'individual', 'stage_type' => 'on_stage',
            'class_group' => 'hs', 'is_enabled' => true,
        ]);
        $offStageItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Magazine Preparation',
            'participant_type' => 'group', 'stage_type' => 'off_stage',
            'class_group' => 'hs', 'is_enabled' => true,
        ]);

        foreach ([$onStageItem, $offStageItem] as $item) {
            $registration = FestRegistration::create([
                'event_id' => $event->id, 'item_id' => $item->id,
                'school_id' => $school->id, 'status' => 'approved',
            ]);
            FestParticipant::create([
                'registration_id' => $registration->id, 'student_id' => $student->id,
                'participant_role' => 'performer',
            ]);
        }

        $analytics = app(FestEventReportAnalyticsService::class, ['event' => $event]);
        $rows = $analytics->studentWiseBrowserRows($school->id);
        $itemsByTitle = collect($rows[0]['items'])->keyBy('item_title');

        $mono = $itemsByTitle->get('Mono Act');
        $this->assertSame('on_stage', $mono['stage_type']);
        $this->assertSame('individual', $mono['participant_type']);

        $magazine = $itemsByTitle->get('Magazine Preparation');
        $this->assertSame('off_stage', $magazine['stage_type']);
        $this->assertSame('group', $magazine['participant_type']);
    }
}
