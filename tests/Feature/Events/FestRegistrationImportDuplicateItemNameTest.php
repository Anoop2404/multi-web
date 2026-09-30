<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestRegistrationImportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Two items in the same event can share the exact same title (the same item name
 * repeated across categories, e.g. "Mono Act" under both Category 3 and Category 4) —
 * a CSV row matched only by item_title used to silently resolve to whichever matching
 * item came first, registering the student into the wrong one. Coverage for the fix:
 * item_id (now pre-filled in the downloadable templates) disambiguates reliably, and a
 * title-only row that's genuinely ambiguous is rejected with a clear error instead of
 * guessed.
 */
class FestRegistrationImportDuplicateItemNameTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, schoolAdmin: User, sahodayaAdmin: User, event: FestEvent, itemA: FestEventItem, itemB: FestEventItem} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Duplicate Item Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'DI', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Duplicate Item School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');
        $sahodayaAdmin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $sahodayaAdmin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Duplicate Item Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'published',
        ]);

        // Two DIFFERENT items, deliberately given the same title -- the real-world case
        // (an item name repeated in more than one category/class-group).
        $itemA = FestEventItem::create(['event_id' => $event->id, 'title' => 'Mono Act', 'class_group' => 'category_3', 'participant_type' => 'individual', 'is_enabled' => true]);
        $itemB = FestEventItem::create(['event_id' => $event->id, 'title' => 'Mono Act', 'class_group' => 'category_4', 'participant_type' => 'individual', 'is_enabled' => true]);

        // Class 12 -- falls under the default scheme's "Category 4" (Classes 11 & 12),
        // matching itemB below, so a registration into itemB by id is actually eligible.
        $schoolClass = SchoolClass::firstOrCreate(['tenant_id' => $school->id, 'name' => '12'], ['display_order' => 1, 'is_active' => true]);
        Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'reg_no' => 'DUP-001', 'name' => 'Duplicate Item Student', 'status' => 'active', 'verified_at' => now(),
        ]);

        return compact('sahodaya', 'school', 'schoolAdmin', 'sahodayaAdmin', 'event', 'itemA', 'itemB');
    }

    private function csvPath(array $headers, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fest-dup-item-test-');
        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }

    public function test_item_id_registers_into_the_exact_item_even_when_its_title_is_shared(): void
    {
        ['school' => $school, 'event' => $event, 'itemB' => $itemB] = $this->fixture();

        $path = $this->csvPath(['item_id', 'item_title', 'reg_no', 'team_name', 'role'], [
            [$itemB->id, '', 'DUP-001', '', 'performer'],
        ]);

        try {
            $result = app(FestRegistrationImportService::class)->importFromCsv($event->fresh(), $school, $path);
        } finally {
            @unlink($path);
        }

        $this->assertSame(1, $result['imported'], json_encode($result));
        $this->assertSame(0, $result['skipped']);
        $this->assertSame($itemB->id, FestRegistration::latest('id')->first()->item_id, 'must land on the exact item the id named, not the other one sharing its title');
    }

    public function test_a_title_only_row_that_matches_two_items_is_rejected_instead_of_guessed(): void
    {
        ['school' => $school, 'event' => $event] = $this->fixture();

        $path = $this->csvPath(['item_id', 'item_title', 'reg_no', 'team_name', 'role'], [
            ['', 'Mono Act', 'DUP-001', '', 'performer'],
        ]);

        try {
            $result = app(FestRegistrationImportService::class)->importFromCsv($event->fresh(), $school, $path);
        } finally {
            @unlink($path);
        }

        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, FestRegistration::count(), 'no guess registration must be created for an ambiguous title');
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('more than one item is titled', $result['errors'][0]);
        $this->assertStringContainsString('item_id', $result['errors'][0]);
    }

    public function test_a_title_that_matches_only_one_item_still_imports_by_title_alone(): void
    {
        ['school' => $school, 'event' => $event] = $this->fixture();

        $unique = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual', 'is_enabled' => true]);

        $path = $this->csvPath(['item_id', 'item_title', 'reg_no', 'team_name', 'role'], [
            ['', 'Solo Song', 'DUP-001', '', 'performer'],
        ]);

        try {
            $result = app(FestRegistrationImportService::class)->importFromCsv($event->fresh(), $school, $path);
        } finally {
            @unlink($path);
        }

        $this->assertSame(1, $result['imported'], json_encode($result));
        $this->assertSame($unique->id, FestRegistration::latest('id')->first()->item_id);
    }

    public function test_the_school_side_template_lists_the_chosen_events_real_items_with_ids(): void
    {
        ['school' => $school, 'schoolAdmin' => $schoolAdmin, 'event' => $event, 'itemA' => $itemA, 'itemB' => $itemB] = $this->fixture();

        $csv = $this->actingAs($schoolAdmin)
            ->get(route('school.kalotsav.import-template', ['tenantId' => $school->id]).'?event_id='.$event->id)
            ->streamedContent();

        $this->assertStringContainsString((string) $itemA->id, $csv);
        $this->assertStringContainsString((string) $itemB->id, $csv);
        $this->assertStringContainsString('Mono Act', $csv);
        // Both rows exist even though the title is identical -- the id is what tells them apart.
        $this->assertSame(2, substr_count($csv, 'Mono Act'));
    }

    public function test_the_school_side_template_falls_back_to_generic_rows_without_an_event(): void
    {
        ['school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->fixture();

        $this->actingAs($schoolAdmin)
            ->get(route('school.kalotsav.import-template', ['tenantId' => $school->id]))
            ->assertOk();
    }

    public function test_the_sahodaya_side_cluster_template_lists_the_events_real_items_with_ids(): void
    {
        ['sahodaya' => $sahodaya, 'sahodayaAdmin' => $sahodayaAdmin, 'event' => $event, 'itemA' => $itemA, 'itemB' => $itemB] = $this->fixture();

        $csv = $this->actingAs($sahodayaAdmin)
            ->get(route('sahodaya.events.registrations.import-template', ['tenantId' => $sahodaya->id, 'event' => $event->id]))
            ->streamedContent();

        $this->assertStringContainsString((string) $itemA->id, $csv);
        $this->assertStringContainsString((string) $itemB->id, $csv);
        $this->assertSame(2, substr_count($csv, 'Mono Act'));
    }
}
