<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\FestMarkEntryController;
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
use ReflectionMethod;
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
            'report_types' => ['judge_sheet', 'result_declaration', 'chest_number_list'],
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
        // chest_number_list are each single-page for one item with no judge panel),
        // proves pages were actually appended from all three, not just the first.
        $this->assertGreaterThanOrEqual(3, $pageCount);
    }

    public function test_skips_a_type_with_nothing_to_include_instead_of_failing_the_whole_merge(): void
    {
        [$sahodaya, $event, $admin, $item] = $this->fixture();

        // sum_sheet (the blank Sum Sheet) only covers multi-judge items -- the fixture
        // item has no configured criteria at all, so it defaults to a single judge and
        // sum_sheet comes up with nothing to include. Mixing it with a type that always
        // succeeds proves one empty type doesn't sink the whole merge.
        $response = $this->actingAs($admin)->get($this->comboUrl($sahodaya, $event, [
            'report_types' => ['chest_number_list', 'sum_sheet'],
            'item_ids' => (string) $item->id,
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        // Only chest_number_list actually contributed -- proves sum_sheet was the one
        // skipped, not that chest_number_list silently failed instead.
        $this->assertStringContainsString('1-reports-merged', urldecode($response->headers->get('content-disposition')));
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

        $response = $this->actingAs($admin)->get($this->comboUrl($sahodaya, $event, ['report_types' => ['chest_number_list']]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /**
     * Reproduces a real production error: a report type generated successfully (200,
     * no exception) but its bytes weren't a real PDF -- "Unable to find PDF file
     * header" from FPDI, e.g. an external PDF-converter hiccup returning an HTML error
     * page instead of PDF content. That crashed the whole merge with a 500 instead of
     * just leaving that one report out. mergePdfByteStrings() is private, so this drives
     * it directly via reflection rather than needing to actually reproduce a broken
     * external converter end-to-end.
     */
    /**
     * mergePdfByteStrings() doesn't touch $this->sahodaya (or anything else set up by
     * SahodayaAdminController's constructor, which needs a real routed request to
     * resolve) -- newInstanceWithoutConstructor() is deliberate here, not a workaround
     * for a real dependency.
     */
    private function controllerForMergeTest(): FestMarkEntryController
    {
        return (new \ReflectionClass(FestMarkEntryController::class))->newInstanceWithoutConstructor();
    }

    public function test_a_non_pdf_byte_string_is_skipped_not_fatal(): void
    {
        $method = new ReflectionMethod(FestMarkEntryController::class, 'mergePdfByteStrings');
        $method->setAccessible(true);

        $realPdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<h1>Real page</h1>')->output();

        [$merged, $included] = $method->invoke($this->controllerForMergeTest(), [
            'judge_sheet' => $realPdf,
            'attendance_sheet' => '<html><body>500 Internal Server Error</body></html>',
        ]);

        $this->assertSame(['judge_sheet'], $included);
        $this->assertStringStartsWith('%PDF', $merged);
    }

    public function test_every_type_producing_non_pdf_bytes_still_errors_cleanly(): void
    {
        $method = new ReflectionMethod(FestMarkEntryController::class, 'mergePdfByteStrings');
        $method->setAccessible(true);

        [$merged, $included] = $method->invoke($this->controllerForMergeTest(), [
            'attendance_sheet' => 'not a pdf at all',
        ]);

        $this->assertSame([], $included);
        $this->assertSame('', $merged);
    }
}
