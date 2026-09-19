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
use Inertia\Testing\AssertableInertia as Assert;
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

        $hsItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'HS Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'results_published_at' => now()]);
        $hssItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'HSS Item', 'participant_type' => 'individual', 'class_group' => 'hss', 'is_enabled' => true, 'results_published_at' => now()]);

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

    /**
     * Unlike the Category & Item-wise Consolidated Report / Category Totals sheets
     * (which drop an excluded category entirely, since those represent the OVERALL
     * championship standing), this Sahodaya still wants to browse/print an excluded
     * category's own points table here -- its schools' totals are real, just not
     * counted toward OVERALL. So the tab stays, flagged via excluded_from_overall
     * rather than removed. This is deliberately scoped to ONLY this report page.
     */
    public function test_a_category_excluded_from_overall_still_gets_its_own_tab_but_flagged(): void
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
        $this->assertContains('lp', $categoryKeys, 'lp is excluded from OVERALL but should still get its own tab/report on this page');

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/category-wise-points")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/Reports/CategoryWisePoints', false)
                ->where('categories', fn ($categories) => collect($categories)->firstWhere('key', 'hs')['excluded_from_overall'] === false
                    && collect($categories)->firstWhere('key', 'lp')['excluded_from_overall'] === true));
    }

    /** The other reports (Category & Item-wise Consolidated / Category Totals) must keep dropping an excluded category entirely -- only this report page changed. */
    public function test_the_consolidated_matrix_still_drops_an_excluded_category_entirely(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Category Points Scope Sahodaya',
            'domain' => 'category-points-scope-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CPS', 'student_data_mode' => 'counts_only']);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Category Points Scope Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
            'aggregation_config' => ['excluded_overall_categories' => ['lp']],
        ]);

        FestEventItem::create(['event_id' => $event->id, 'title' => 'HS Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
        FestEventItem::create(['event_id' => $event->id, 'title' => 'LP Item', 'participant_type' => 'individual', 'class_group' => 'lp', 'is_enabled' => true]);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/category-item-matrix")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/Reports/CategoryItemMatrix', false)
                ->where('categories', fn ($categories) => collect($categories)->pluck('key')->contains('hs')
                    && ! collect($categories)->pluck('key')->contains('lp')));
    }

    public function test_pdf_preview_downloads_for_a_category(): void
    {
        [$sahodaya, $event, $admin, $school] = $this->fixture();

        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'HS Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'results_published_at' => now()]);
        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Student', 'admission_no' => 'S1']);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/category-wise-points/hs/pdf?preview=1");

        $response->assertOk();
    }

    /**
     * Same dompdf writing-mode limitation as the consolidated matrix's own PDF -- see
     * its test's docblock for the full explanation. A first fix (a plain, non-absolute
     * rotated span sized to its own full text width) avoided clipping and overlap, but
     * at the cost of every item column becoming as wide as its own (long) pre-rotation
     * text box -- confirmed live: item columns were noticeably wider than needed, with
     * a lot of empty space next to each narrow rotated text. The real fix keeps
     * position:absolute (so the wide pre-rotation box never forces the table layout to
     * reserve that width) but locks each item <th> to an explicit min/max-width -- the
     * true cause of the earlier "overlap" was dompdf's automatic table layout leaving
     * item columns unpredictably narrow/inconsistent width without that lock, not
     * absolute positioning itself. Confirmed by rendering a 10-item PDF (including a
     * long "Classical Music (Karnatic)" title) and reading it back: narrow, evenly
     * spaced, non-overlapping, non-clipped columns.
     */
    public function test_pdf_item_header_uses_dompdf_compatible_rotation_not_writing_mode(): void
    {
        $css = file_get_contents(resource_path('views/fest/reports/category-points-table.blade.php'));

        $this->assertStringNotContainsString('writing-mode', $css);
        $this->assertStringContainsString('rotate(-90deg)', $css);
        $this->assertStringContainsString('position:absolute', $css);
        $this->assertStringContainsString('max-width:{{ $itemColWidth }}px', $css, 'each item column must be locked to a fixed (if dynamically computed, see categoryWisePointsPdf()) width, or dompdf\'s automatic table layout can leave columns unpredictably narrow and overlapping');
    }

    /** Ten items in one category, the scenario that first revealed the column-overlap bug above -- must still render without error. */
    public function test_pdf_downloads_with_many_items_in_one_category(): void
    {
        [$sahodaya, $event, $admin, $school] = $this->fixture();

        $titles = ['Light Music - Malayalam', 'Light Music - Malayalam', 'Classical Music (Karnatic)', 'Folk Dance', 'Folk Dance', 'Group Dance', 'Bharatanatyam', 'Bharatanatyam', 'Mohiniyattam', 'Mono Act'];
        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        foreach ($titles as $i => $title) {
            $item = FestEventItem::create(['event_id' => $event->id, 'title' => $title, 'item_code' => (string) (209 + $i), 'participant_type' => 'individual', 'gender' => $i % 2 ? 'female' : 'male', 'class_group' => 'hs', 'is_enabled' => true]);
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => "Student {$i}", 'admission_no' => "MO{$i}"]);
            $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
            $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
            FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);
        }

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/category-wise-points/hs/pdf?preview=1");

        $response->assertOk();
    }

    public function test_xls_download_contains_rotated_item_headers_and_real_points(): void
    {
        [$sahodaya, $event, $admin, $school] = $this->fixture();

        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'HS Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'results_published_at' => now()]);
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

    /**
     * The "Totals Only" sibling of the full item-breakdown PDF/xls above -- just
     * School/Total/Rank for the category, no per-item columns, Rank trailing on the
     * right (matching the all-categories Category Totals report's layout). An
     * unpublished item's points must not reach this total either -- same publish gate
     * as schoolItemPointsMatrix()/categorySchoolPointsTable().
     */
    public function test_summary_pdf_and_xls_show_only_school_total_rank_and_respect_publish_status(): void
    {
        [$sahodaya, $event, $admin, $school] = $this->fixture();

        $publishedItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'Published HS Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'results_published_at' => now()]);
        $unpublishedItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'Unpublished HS Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);

        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Published Student', 'admission_no' => 'SP1']);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $publishedItem->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
        FestMark::create(['event_id' => $event->id, 'item_id' => $publishedItem->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);

        $studentU = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Unpublished Student', 'admission_no' => 'SU1']);
        $registrationU = FestRegistration::create(['event_id' => $event->id, 'item_id' => $unpublishedItem->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participantU = FestParticipant::create(['registration_id' => $registrationU->id, 'student_id' => $studentU->id, 'participant_role' => 'performer']);
        FestMark::create(['event_id' => $event->id, 'item_id' => $unpublishedItem->id, 'participant_id' => $participantU->id, 'position' => 1, 'grade' => 'A']);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/category-wise-points/hs/summary-pdf?preview=1")
            ->assertOk();

        $xml = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/category-wise-points/hs/summary-xls")
            ->streamedContent();

        $this->assertStringContainsString('>School<', $xml);
        $this->assertStringContainsString('>Total<', $xml);
        $this->assertStringContainsString('>Rank<', $xml);
        $this->assertStringNotContainsString('Published HS Item', $xml, 'the summary sheet must not list per-item columns at all');
        $this->assertStringNotContainsString('Unpublished HS Item', $xml);

        $publishedOnlyTable = app(FestEventReportAnalyticsService::class, ['event' => $event])->categorySchoolPointsTable('hs');
        $schoolRow = collect($publishedOnlyTable['schools'])->firstWhere('school_id', $school->id);
        $this->assertSame(1, $schoolRow['rank']);
        $this->assertGreaterThan(0, $schoolRow['subtotal'], 'the published item\'s points must still count');

        // Publishing the second item too must raise the total -- proves the gap above
        // really was the unpublished item's points being excluded, not just a fluke of
        // this fixture's numbers.
        $unpublishedItem->update(['results_published_at' => now()]);
        $bothPublishedTable = app(FestEventReportAnalyticsService::class, ['event' => $event])->categorySchoolPointsTable('hs');
        $schoolRowBothPublished = collect($bothPublishedTable['schools'])->firstWhere('school_id', $school->id);
        $this->assertGreaterThan($schoolRow['subtotal'], $schoolRowBothPublished['subtotal']);
    }

    public function test_points_table_json_endpoint_returns_items_and_school_points(): void
    {
        [$sahodaya, $event, $admin, $school] = $this->fixture();

        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'HS Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'results_published_at' => now()]);
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

    /**
     * The user's real Category 3 has 59 items -- explicitly wants this kept to ONE
     * page (unlike the Consolidated matrix, which paginates), even if that means a
     * small font and narrow columns. Verified live by rendering this exact 59-item
     * scenario and reading the PDF back: one page, all 59 columns present, correctly
     * rotated, no overlap or clipping. This just guards against a crash/regression --
     * page-count and visual layout aren't practical to assert from a compiled PDF's
     * binary bytes in an automated test.
     */
    public function test_pdf_downloads_as_one_page_with_59_items(): void
    {
        [$sahodaya, $event, $admin, $school] = $this->fixture();

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        foreach (range(1, 59) as $i) {
            $item = FestEventItem::create(['event_id' => $event->id, 'title' => "Item {$i} Long Name Here", 'item_code' => (string) (300 + $i), 'participant_type' => 'individual', 'gender' => $i % 2 ? 'female' : 'male', 'class_group' => 'hs', 'is_enabled' => true]);
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => "Student {$i}", 'admission_no' => "D{$i}"]);
            $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
            $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
            FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);
        }

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/category-wise-points/hs/pdf?preview=1");

        $response->assertOk();
    }
}
