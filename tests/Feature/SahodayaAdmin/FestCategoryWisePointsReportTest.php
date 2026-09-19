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
 * Category-wise Points is the "separate, unmerged" companion to the Consolidated
 * matrix report — a category the admin merged into another for the combined
 * championship (aggregation_config.championship_category_map) must still show as its
 * own tab and its own point-table PDF here, since that merge is specifically an
 * overall/combined-total convention (see FestCategoryMerge's docblock), not a
 * statement the category never existed on its own.
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

    public function test_a_merged_category_still_gets_its_own_unmerged_row_here(): void
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
        $this->assertContains('hss', $categoryKeys, 'hss must stay its own category here even though it is merged into hs for the combined championship');

        $hsTable = $analytics->categorySchoolPointsTable('hs');
        $this->assertCount(1, $hsTable['items']);
        $this->assertSame('HS Item', $hsTable['items'][0]['title']);

        $hssTable = $analytics->categorySchoolPointsTable('hss');
        $this->assertCount(1, $hssTable['items']);
        $this->assertSame('HSS Item', $hssTable['items'][0]['title']);
        $this->assertGreaterThan(0, $hssTable['schools'][0]['subtotal']);
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
}
