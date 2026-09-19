<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestEventReportAnalyticsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Category-wise Points now applies the same merge rule
 * (aggregation_config.championship_category_map) and exclude rule
 * (aggregation_config.excluded_overall_categories) as the Consolidated matrix report —
 * a category merged into another for the combined championship folds into its
 * target's tab here too instead of keeping its own, and a category excluded from
 * OVERALL doesn't get a tab (or a downloadable report) here at all.
 */
class FestCategoryWisePointsReportTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: FestEvent, 2: User, 3: Tenant} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Category Points Sahodaya',
            'domain' => 'category-points-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CP', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Category Points School', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Category Points Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
            'aggregation_config' => [
                'championship_category_map' => ['hss' => 'hs'],
            ],
        ]);

        return [$sahodaya, $event, $admin, $school];
    }

    public function test_a_merged_category_folds_into_its_target_tab_here(): void
    {
        [$sahodaya, $event, $admin, $school] = $this->fixture();

        $hsItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'HS Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
        $hssItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'HSS Item', 'participant_type' => 'individual', 'class_group' => 'hss', 'is_enabled' => true]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        foreach ([$hsItem, $hssItem] as $i => $item) {
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => "Student {$i}", 'admission_no' => "S{$i}"]);
            $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
            $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
            FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);
        }

        $analytics = app(FestEventReportAnalyticsService::class, ['event' => $event]);
        $categoryKeys = collect($analytics->categoryWiseItemRows())->keys()->all();

        $this->assertContains('hs', $categoryKeys);
        $this->assertNotContains('hss', $categoryKeys, 'hss should have collapsed into the hs merge target, matching the consolidated matrix');

        $hsTable = $analytics->categorySchoolPointsTable('hs');
        $this->assertCount(2, $hsTable['items'], 'the hs tab should list both the hs item and the merged-in hss item');
        $this->assertSame(['HS Item', 'HSS Item'], collect($hsTable['items'])->pluck('title')->all());
        $this->assertGreaterThan(0, $hsTable['schools'][0]['subtotal']);
    }

    public function test_a_category_excluded_from_overall_has_no_tab_here(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Category Points Exclude Sahodaya',
            'domain' => 'category-points-exclude-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CPE', 'student_data_mode' => 'counts_only']);
        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Category Points Exclude School', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Category Points Exclude Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
            'aggregation_config' => ['excluded_overall_categories' => ['lp']],
        ]);

        FestEventItem::create(['event_id' => $event->id, 'title' => 'HS Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
        FestEventItem::create(['event_id' => $event->id, 'title' => 'LP Item', 'participant_type' => 'individual', 'class_group' => 'lp', 'is_enabled' => true]);

        $analytics = app(FestEventReportAnalyticsService::class, ['event' => $event]);
        $categoryKeys = collect($analytics->categoryWiseItemRows())->keys()->all();

        $this->assertContains('hs', $categoryKeys);
        $this->assertNotContains('lp', $categoryKeys, 'lp is excluded from OVERALL and should not get its own tab/report here');
    }

    public function test_pdf_preview_downloads_for_a_category(): void
    {
        [$sahodaya, $event, $admin, $school] = $this->fixture();

        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'HS Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Student', 'admission_no' => 'S1']);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/category-wise-points/hs/pdf?preview=1");

        $response->assertOk();
    }

    public function test_xls_download_contains_rotated_item_headers_and_real_points(): void
    {
        [$sahodaya, $event, $admin, $school] = $this->fixture();

        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'HS Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Student', 'admission_no' => 'S1']);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);

        $xml = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/category-wise-points/hs/xls")
            ->streamedContent();

        $this->assertStringContainsString(strtoupper($school->name), $xml);
        $this->assertStringContainsString('ss:Rotate="90"', $xml);
        $this->assertMatchesRegularExpression('/<Cell><Data ss:Type="Number">[1-9]\d*<\/Data><\/Cell>/', $xml);
    }

    public function test_points_table_json_endpoint_returns_items_and_school_points(): void
    {
        [$sahodaya, $event, $admin, $school] = $this->fixture();

        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'HS Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Student', 'admission_no' => 'S1']);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);

        $response = $this->actingAs($admin)
            ->getJson("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/category-wise-points/hs/table");

        $response->assertOk()
            ->assertJsonPath('items.0.title', 'HS Item')
            ->assertJsonPath('schools.0.school_id', $school->id);
        $this->assertGreaterThan(0, $response->json('schools.0.subtotal'));
    }
}
