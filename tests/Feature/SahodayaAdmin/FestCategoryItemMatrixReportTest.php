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
 * The new consolidated category-wise/item-wise report — school rows, item columns
 * grouped by category, category subtotal + overall grand-total columns. Covers the
 * interactive page and both export formats (xls, pdf) end to end, plus the exact
 * points-per-cell computation the user's example sheet needs to match the leaderboard.
 */
class FestCategoryItemMatrixReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_interactive_page_returns_correct_matrix_shape_and_points(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Matrix Report Sahodaya',
            'domain' => 'matrix-report-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'MR', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Test School', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Matrix Report Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual',
            'class_group' => 'hs', 'is_enabled' => true,
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Test Student', 'admission_no' => 'S1']);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/category-item-matrix")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/Reports/CategoryItemMatrix', false)
                ->where('categories.0.heads.0.items.0.id', $item->id)
                ->where('schools.0.school_id', $school->id)
                ->where('schools.0.rank', 1)
                ->has('schools.0.points_by_item.'.$item->id)
                ->has('schools.0.category_totals')
                ->has('schools.0.overall'));
    }

    /**
     * Reproduces a real gap: the consolidated matrix built its category columns
     * straight from each item's own class_group/age_group and summed every column
     * into OVERALL, ignoring both admin-configured settings that already drive the
     * public scoreboard/championship — aggregation_config.championship_category_map
     * (merge one category into another) and .excluded_overall_categories (leave a
     * category out of the combined total). A merged category showed as two separate
     * columns instead of one, and an excluded category's points still counted toward
     * OVERALL.
     */
    public function test_merged_categories_collapse_and_excluded_categories_are_dropped_from_overall(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Matrix Merge Sahodaya',
            'domain' => 'matrix-merge-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'MM', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Merge Test School', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Matrix Merge Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
            'aggregation_config' => [
                'championship_category_map' => ['hss' => 'hs'],
                'excluded_overall_categories' => ['lp'],
            ],
        ]);

        $hsItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'HS Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
        $hssItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'HSS Item', 'participant_type' => 'individual', 'class_group' => 'hss', 'is_enabled' => true]);
        $lpItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'LP Item', 'participant_type' => 'individual', 'class_group' => 'lp', 'is_enabled' => true]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        foreach ([$hsItem, $hssItem, $lpItem] as $i => $item) {
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => "Student {$i}", 'admission_no' => "S{$i}"]);
            $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
            $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
            FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);
        }

        $matrix = app(\App\Services\Events\FestEventReportAnalyticsService::class, ['event' => $event])->schoolItemPointsMatrix();

        $categoryKeys = collect($matrix['categories'])->pluck('key')->all();
        $this->assertContains('hs', $categoryKeys);
        $this->assertNotContains('hss', $categoryKeys, 'hss should have collapsed into the hs merge target');

        $hsCategory = collect($matrix['categories'])->firstWhere('key', 'hs');
        $this->assertCount(2, collect($hsCategory['heads'])->flatMap(fn ($h) => $h['items']), 'merged hs column should list both the hs and the merged-in hss item');

        $lpCategory = collect($matrix['categories'])->firstWhere('key', 'lp');
        $this->assertTrue($lpCategory['excluded_from_overall']);
        $this->assertFalse($hsCategory['excluded_from_overall']);

        $schoolRow = collect($matrix['schools'])->firstWhere('school_id', $school->id);
        $this->assertGreaterThan(0, $schoolRow['category_totals']['lp'], 'lp keeps its own subtotal even though it is excluded from OVERALL');
        $this->assertSame($schoolRow['category_totals']['hs'], $schoolRow['overall'], 'OVERALL should equal only the non-excluded (merged) hs total, not hs + lp');
    }

    public function test_xls_export_downloads(): void
    {
        [$sahodaya, $event, $admin] = $this->makeMinimalEvent();

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/export/category-item-matrix-xls");

        $response->assertOk();
    }

    /**
     * The xls/pdf export tests above only ever asserted assertOk() on a zero-item,
     * zero-mark fixture — assertOk() passes for a StreamedResponse regardless of
     * whether its content is actually populated, and there were never any real marks
     * to reveal a blank-cell bug either way. This proves real point data actually
     * reaches the exported file: streamedContent() (not getContent(), which returns
     * empty for an unsent StreamedResponse in tests) must contain both the school name
     * and a non-zero point value.
     */
    public function test_xls_export_contains_real_school_and_point_data(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $sahodaya = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Matrix XLS Data Sahodaya', 'domain' => 'matrix-xls-data-'.Str::random(8).'.test', 'is_active' => true]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'MX', 'student_data_mode' => 'counts_only']);
        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Matrix XLS Data School', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Matrix XLS Data Fest', 'event_type' => 'kalolsavam', 'level_round' => 'sahodaya', 'status' => 'ongoing']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Matrix XLS Data Student', 'admission_no' => 'MXD1']);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);

        // A second school so the alternating (zebra-striped) row style below has a
        // second row to differ from the first.
        $schoolB = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Matrix XLS Data School B', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);
        $studentB = Student::create(['tenant_id' => $schoolB->id, 'school_class_id' => $schoolClass->id, 'name' => 'Matrix XLS Data Student B', 'admission_no' => 'MXD2']);
        $registrationB = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schoolB->id, 'status' => 'approved']);
        $participantB = FestParticipant::create(['registration_id' => $registrationB->id, 'student_id' => $studentB->id, 'participant_role' => 'performer']);
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participantB->id, 'position' => 2, 'grade' => 'A']);

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/export/category-item-matrix-xls");

        $xml = $response->streamedContent();
        $this->assertStringContainsString('MATRIX XLS DATA SCHOOL', $xml);
        $this->assertMatchesRegularExpression('/<Cell><Data ss:Type="Number">[1-9]\d*<\/Data><\/Cell>/', $xml, 'expected at least one non-zero point cell in the exported XLS');
        $this->assertStringContainsString('ss:Rotate="90"', $xml, 'item-name header columns should render rotated, matching the web page/PDF');
        $this->assertStringContainsString('<Cell ss:StyleID="header-vertical">', $xml, 'the item column header cell should use the rotated style, not the plain header style');
        $this->assertMatchesRegularExpression('/<Cell ss:StyleID="header"><Data ss:Type="String">School<\/Data><\/Cell>/', $xml, 'the School column header must stay horizontal, not rotated');
        $this->assertStringContainsString('<Row ss:StyleID="body">', $xml, 'the first school row should use the plain body style');
        $this->assertStringContainsString('<Row ss:StyleID="body-alt">', $xml, 'the second school row should use the shaded alternate style');
        $this->assertMatchesRegularExpression('/<Cell ss:StyleID="header"><Data ss:Type="String">Subtotal<\/Data><\/Cell>/', $xml, 'the category subtotal header should read "Subtotal" (short enough to stay narrow), not the bare "Sub" abbreviation or the long unrotated "Category ... Subtotal" string that forced one oversized column');
        $this->assertMatchesRegularExpression('/<Cell ss:StyleID="header"><Data ss:Type="String">Rank<\/Data><\/Cell>/', $xml, 'a Rank column should be the first header, matching the per-category points table');

        $matrix = app(\App\Services\Events\FestEventReportAnalyticsService::class, ['event' => $event])->schoolItemPointsMatrix();
        $schoolRow = collect($matrix['schools'])->firstWhere('school_id', $school->id);
        $schoolBRow = collect($matrix['schools'])->firstWhere('school_id', $schoolB->id);
        $this->assertSame(1, $schoolRow['rank'], 'the higher-scoring school (position 1) should rank 1');
        $this->assertSame(2, $schoolBRow['rank'], 'the lower-scoring school (position 2) should rank 2, not tied with rank 1');

        $this->assertMatchesRegularExpression('/<Cell ss:StyleID="cat-a">/', $xml, 'the item columns should be shaded with the first category band colour so a reader can tell one category\'s columns apart from the next');
        $this->assertMatchesRegularExpression('/<Cell ss:StyleID="sub">/', $xml, 'the Sub column cells should use the bold highlighted "sub" style, matching the PDF/web Sub column');
        $this->assertMatchesRegularExpression('/<Cell ss:StyleID="overall">/', $xml, 'the OVERALL column cells should use the bold highlighted "overall" style, matching the PDF/web OVERALL column');
    }

    public function test_pdf_export_downloads(): void
    {
        [$sahodaya, $event, $admin] = $this->makeMinimalEvent();

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/export/category-item-matrix-pdf");

        $response->assertOk();
    }

    /** @return array{0: Tenant, 1: FestEvent, 2: User} */
    private function makeMinimalEvent(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Matrix Export Sahodaya',
            'domain' => 'matrix-export-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'ME', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Matrix Export Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        return [$sahodaya, $event, $admin];
    }

    /**
     * Reproduces a real production bug (Wayanad Sahodaya): an item split across
     * phases — the hub's own canonical item row, plus a phase leaf's inherited copy of
     * that same item (FestEventItem.inherited_from_item_id) — showed as two duplicate
     * columns with the identical title/code when viewed at the combined hub level,
     * since the matrix's item query was FestEventItem::whereIn('event_id',
     * reportableEventIds()), which returns every phase's copy as its own row.
     */
    public function test_an_item_inherited_onto_a_phase_leaf_does_not_duplicate_in_the_hub_level_matrix(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Phase Dup Sahodaya',
            'domain' => 'phase-dup-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'PD', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Phase Dup School', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Phase Dup Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $hubItem = FestEventItem::create(['event_id' => $hub->id, 'title' => 'Solo Song', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);

        $leaf = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Phase Dup Fest — Phase 1', 'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id, 'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        // The leaf's own inherited copy of the hub's item — same title/code, a
        // different row, linked via inherited_from_item_id.
        $leafItem = FestEventItem::create([
            'event_id' => $leaf->id, 'title' => 'Solo Song', 'participant_type' => 'individual',
            'class_group' => 'hs', 'is_enabled' => true, 'inherited_from_item_id' => $hubItem->id,
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Phase Dup Student', 'admission_no' => 'PD1']);

        // The mark is recorded against the LEAF's own item/event, as it would be for a
        // real phase competition.
        $registration = FestRegistration::create(['event_id' => $leaf->id, 'item_id' => $leafItem->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
        FestMark::create(['event_id' => $leaf->id, 'item_id' => $leafItem->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);

        // Viewed at the HUB level, combining hub + leaf.
        $analytics = app(FestEventReportAnalyticsService::class, ['event' => $hub]);
        $matrix = $analytics->schoolItemPointsMatrix();

        $allItemIds = collect($matrix['categories'])
            ->flatMap(fn ($cat) => collect($cat['heads'])->flatMap(fn ($h) => $h['items']))
            ->pluck('id');

        $this->assertCount(1, $allItemIds, 'Solo Song must appear as exactly one column, not once per phase');

        $schoolRow = collect($matrix['schools'])->firstWhere('school_id', $school->id);
        $this->assertNotNull($schoolRow, 'the leaf-recorded mark must still roll up to a school row at the hub level');
        $this->assertGreaterThan(0, $schoolRow['overall']);
    }

    /**
     * A combined (multi-phase/region) event's consolidated matrix can run to 100+
     * item columns, too wide for one printed page — the PDF now splits into
     * page-sized chunks (paginateMatrixColumns()) instead of cramming everything, or
     * silently clipping, onto a single sheet.
     */
    public function test_paginate_matrix_columns_splits_a_wide_category_and_flags_continuation(): void
    {
        // One category with 5 items in a single head, chunked 2-per-page -> 3 pages.
        $items = collect(range(1, 5))->map(fn ($i) => ['id' => $i, 'title' => "Item {$i}"])->all();
        $categories = [[
            'key' => 'hs', 'label' => 'Category 3', 'excluded_from_overall' => false,
            'heads' => [['head_label' => 'General', 'items' => $items]],
        ]];

        $pages = FestEventReportAnalyticsService::paginateMatrixColumns($categories, perPage: 2);

        $this->assertCount(3, $pages);

        // Page 1: items 1-2, category just starting -> not a continuation, not complete.
        $this->assertFalse($pages[0]['categories'][0]['is_continuation']);
        $this->assertFalse($pages[0]['categories'][0]['is_complete_here']);
        $this->assertFalse($pages[0]['is_last_page']);
        $this->assertSame([1, 2], collect($pages[0]['categories'][0]['heads'][0]['items'])->pluck('id')->all());

        // Page 2: items 3-4, category continues from page 1, still not complete.
        $this->assertTrue($pages[1]['categories'][0]['is_continuation']);
        $this->assertFalse($pages[1]['categories'][0]['is_complete_here']);
        $this->assertSame([3, 4], collect($pages[1]['categories'][0]['heads'][0]['items'])->pluck('id')->all());

        // Page 3 (last): item 5 only, category now complete -> its Sub column belongs here.
        $this->assertTrue($pages[2]['categories'][0]['is_continuation']);
        $this->assertTrue($pages[2]['categories'][0]['is_complete_here']);
        $this->assertTrue($pages[2]['is_last_page']);
        $this->assertSame([5], collect($pages[2]['categories'][0]['heads'][0]['items'])->pluck('id')->all());
    }

    public function test_paginate_matrix_columns_keeps_everything_on_one_page_when_it_fits(): void
    {
        $items = collect(range(1, 3))->map(fn ($i) => ['id' => $i, 'title' => "Item {$i}"])->all();
        $categories = [[
            'key' => 'hs', 'label' => 'Category 3', 'excluded_from_overall' => false,
            'heads' => [['head_label' => 'General', 'items' => $items]],
        ]];

        $pages = FestEventReportAnalyticsService::paginateMatrixColumns($categories, perPage: 18);

        $this->assertCount(1, $pages);
        $this->assertTrue($pages[0]['is_last_page']);
        $this->assertTrue($pages[0]['categories'][0]['is_complete_here']);
        $this->assertFalse($pages[0]['categories'][0]['is_continuation']);
    }

    /** Real multi-item PDF download (not the zero-item makeMinimalEvent() fixture) must still succeed once paginated. */
    public function test_pdf_export_downloads_and_paginates_with_many_items(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $sahodaya = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Matrix Paginate Sahodaya', 'domain' => 'matrix-paginate-'.Str::random(8).'.test', 'is_active' => true]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'MP', 'student_data_mode' => 'counts_only']);
        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Matrix Paginate School', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Matrix Paginate Fest', 'event_type' => 'kalolsavam', 'level_round' => 'sahodaya', 'status' => 'ongoing']);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        foreach (range(1, 25) as $i) {
            $item = FestEventItem::create(['event_id' => $event->id, 'title' => "Item {$i}", 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => "Student {$i}", 'admission_no' => "MP{$i}"]);
            $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
            $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
            FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);
        }

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/export/category-item-matrix-pdf");

        $response->assertOk();
    }

    /**
     * The interactive web page still shows an excluded category (with a † marker) so
     * an admin can see the full picture — but the downloaded xls/pdf are meant to
     * represent the school's official standing, so this Sahodaya wants that category's
     * columns left out of the file entirely, not just left out of OVERALL.
     */
    public function test_excluded_category_is_left_out_of_the_downloaded_xls_and_pdf(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Matrix Exclude Download Sahodaya',
            'domain' => 'matrix-exclude-dl-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'MED', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Exclude Download School', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Matrix Exclude Download Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
            'aggregation_config' => ['excluded_overall_categories' => ['lp']],
        ]);

        $hsItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'Kept HS Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
        $lpItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'Hidden LP Item', 'participant_type' => 'individual', 'class_group' => 'lp', 'is_enabled' => true]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        foreach ([$hsItem, $lpItem] as $i => $item) {
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => "Exclude Student {$i}", 'admission_no' => "ED{$i}"]);
            $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
            $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
            FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);
        }

        // The compiled PDF is a binary/compressed stream (dompdf FlateDecode), so text
        // can't be reliably grepped out of it -- assert the download doesn't crash
        // (proves the filtered-down category set still renders fine), and verify the
        // exact filter categoryItemMatrixPdf()/categoryItemMatrixXls() apply
        // (collect($matrix['categories'])->reject(fn ($c) => $c['excluded_from_overall']))
        // drops 'lp' and keeps 'hs' at the data level directly.
        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/export/category-item-matrix-pdf")
            ->assertOk();

        $matrix = app(\App\Services\Events\FestEventReportAnalyticsService::class, ['event' => $event])->schoolItemPointsMatrix();
        $downloadedCategoryKeys = collect($matrix['categories'])->reject(fn (array $c) => $c['excluded_from_overall'])->pluck('key')->all();
        $this->assertContains('hs', $downloadedCategoryKeys);
        $this->assertNotContains('lp', $downloadedCategoryKeys, 'the excluded lp category must not appear in the downloaded report at all');

        // XLS is plain SpreadsheetML XML (not compressed), so its item-name headers can
        // be checked directly in the streamed content.
        $xls = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/export/category-item-matrix-xls")
            ->streamedContent();
        $this->assertStringContainsString('Kept HS Item', $xls);
        $this->assertStringNotContainsString('Hidden LP Item', $xls);
    }

    /**
     * dompdf doesn't support the CSS `writing-mode` property at all -- the item-name
     * header's `writing-mode:vertical-rl` was silently ignored, leaving only
     * `transform:rotate(180deg)` applied to otherwise-horizontal text, which renders as
     * upside-down horizontal text instead of vertical text (confirmed by actually
     * rendering the PDF and reading it back). The working dompdf technique instead
     * rotates a wide, absolutely-positioned span with `transform:rotate(-90deg)` so
     * dompdf doesn't clip it to a too-small pre-rotation box. This just guards the
     * blade source against a well-intentioned revert back to the broken
     * writing-mode approach.
     */
    /**
     * dompdf doesn't support the CSS `writing-mode` property at all -- the item-name
     * header's `writing-mode:vertical-rl` was silently ignored, leaving only
     * `transform:rotate(180deg)` applied to otherwise-horizontal text, which renders as
     * upside-down horizontal text instead of vertical text (confirmed by actually
     * rendering the PDF and reading it back). A second attempt (transform:rotate(-90deg)
     * on a plain, non-absolute span sized to its own full text width) fixed that, but
     * every item column became as wide as its own (long) pre-rotation text box --
     * confirmed live: noticeably wide columns with a lot of empty space next to each
     * narrow rotated text. The working technique restores position:absolute (so the
     * wide pre-rotation box never forces the column that wide) but locks each item
     * <th> to an explicit min/max-width -- an EARLIER absolute-positioning attempt
     * without that lock had every column's rotated text collapse onto the same
     * position and overlap, because dompdf's automatic table layout left item columns
     * unpredictably narrow/inconsistent width, not because absolute positioning itself
     * is broken in table cells. Verified by rendering a 25-item, 2-page report and
     * reading it back: narrow, evenly spaced, non-overlapping, non-clipped columns on
     * every page, including the page with Sub/OVERALL columns.
     */
    public function test_pdf_item_header_uses_dompdf_compatible_rotation_not_writing_mode(): void
    {
        $css = file_get_contents(resource_path('views/fest/reports/category-item-matrix.blade.php'));

        $this->assertStringNotContainsString('writing-mode', $css, 'dompdf does not support writing-mode -- it silently no-ops, leaving text upside-down instead of vertical');
        $this->assertStringContainsString('rotate(-90deg)', $css);
        $this->assertStringContainsString('position:absolute', $css);
        $this->assertStringContainsString('max-width:24px', $css, 'each item column must be locked to a fixed width, or dompdf\'s automatic table layout can leave columns unpredictably narrow and overlapping');
    }
}
