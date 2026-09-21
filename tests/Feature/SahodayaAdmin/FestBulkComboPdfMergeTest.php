<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Download checked reports" used to fire one popup/download per checked report type,
 * which browsers silently cap at one tab/popup per click -- this merges every checked
 * type's own already-generated PDF into a single file (FPDI page-import, not a rewrite
 * of any report's own generation logic) so there's only ever one file to open.
 */
class FestBulkComboPdfMergeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: FestEvent, 2: User, 3: FestEventItem} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Combo Merge Sahodaya',
            'domain' => 'combo-merge-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CM', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Combo Merge School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Combo Merge Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => '101',
            'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true,
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Combo Student', 'admission_no' => 'CM1']);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'main']);

        return [$sahodaya, $event, $admin, $item];
    }

    private function comboUrl(Tenant $sahodaya, FestEvent $event, array $query): string
    {
        return "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/bulk-combo-pdf?".http_build_query($query);
    }

    public function test_merges_several_report_types_into_one_multi_page_pdf(): void
    {
        [$sahodaya, $event, $admin, $item] = $this->fixture();

        $response = $this->actingAs($admin)->get($this->comboUrl($sahodaya, $event, [
            'report_types' => ['judge_sheet', 'result_declaration', 'items_list'],
            'item_ids' => (string) $item->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('3-reports-merged', urldecode($response->headers->get('content-disposition')));

        // %PDF magic bytes -- confirms FPDI actually produced a real PDF, not garbage.
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $tmpFile = tempnam(sys_get_temp_dir(), 'merge-test-').'.pdf';
        file_put_contents($tmpFile, $response->getContent());
        $pageCount = (new \setasign\Fpdi\Fpdi())->setSourceFile($tmpFile);
        unlink($tmpFile);

        // At least 3 pages -- one per merged report type (judge_sheet/result_declaration/
        // items_list are each single-page for one item with no judge panel), proves pages
        // were actually appended from all three, not just the first.
        $this->assertGreaterThanOrEqual(3, $pageCount);
    }

    public function test_skips_a_type_with_nothing_to_include_instead_of_failing_the_whole_merge(): void
    {
        [$sahodaya, $event, $admin, $item] = $this->fixture();

        // sum_sheet (Digital Sum Sheet / cumulativeSheet()) 422s when no item selection
        // resolves at all under some param combos -- mixing it with a type that always
        // succeeds proves one failure doesn't sink the whole merge.
        $response = $this->actingAs($admin)->get($this->comboUrl($sahodaya, $event, [
            'report_types' => ['items_list', 'sum_sheet'],
            'item_ids' => (string) $item->id,
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_no_report_types_selected_is_rejected(): void
    {
        [$sahodaya, $event, $admin] = $this->fixture();

        $response = $this->actingAs($admin)->get($this->comboUrl($sahodaya, $event, ['report_types' => []]));

        $response->assertStatus(422);
    }

    public function test_no_filters_merges_for_every_enabled_item_in_the_event(): void
    {
        [$sahodaya, $event, $admin] = $this->fixture();

        $response = $this->actingAs($admin)->get($this->comboUrl($sahodaya, $event, ['report_types' => ['items_list']]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
