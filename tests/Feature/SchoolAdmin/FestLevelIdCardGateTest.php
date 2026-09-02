<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestRegistrationBatch;
use App\Models\FestSchoolEventFee;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestRegistrationBatchFeeService;
use App\Support\AcademicYear;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression test for the bug where a school whose Level 1 invoice was paid and approved
 * still saw "Payment pending" and zero participants on the Kalotsavam ID cards page,
 * because the page gated on the whole-event fee — which, on a levelled
 * (phased_regional_billing) event, only clears once EVERY level is paid.
 */
class FestLevelIdCardGateTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Level Gate Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'LG', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Level Gate School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $school->id]);
        $admin->assignRole('school_admin');

        Registration::create([
            'school_id' => $school->id,
            'academic_year' => AcademicYear::forSahodaya($sahodaya->id),
            'registration_status' => 'completed',
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Level Gate Kalotsavam',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
            'workflow_mode' => FestPhasedWorkflowService::MODE,
            'phase_mode_enabled' => true,
            'fee_settings' => [
                'fee_model' => 'per_item',
                'per_item_amount' => 100,
                'require_verified_students' => false,
            ],
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);
        $levels = [];
        $items = [];

        foreach ([['level_1', 'Level 1', 'Digi Fest', 1], ['level_2', 'Level 2', 'Off Stage', 2]] as [$code, $name, $phaseName, $order]) {
            $batch = FestRegistrationBatch::create([
                'event_id' => $event->id,
                'code' => $code,
                'name' => $name,
                'sort_order' => $order,
                'status' => 'registration_open',
            ]);

            $phase = FestEventPhase::create([
                'event_id' => $event->id,
                'registration_batch_id' => $batch->id,
                'name' => $phaseName,
                'code' => Str::slug($phaseName),
                'sort_order' => $order,
            ]);

            $item = FestEventItem::create([
                'event_id' => $event->id,
                'title' => $name.' Item',
                'participant_type' => 'individual',
                'is_enabled' => true,
                'phase_id' => $phase->id,
            ]);

            $student = Student::create([
                'tenant_id' => $school->id,
                'school_class_id' => $schoolClass->id,
                'name' => $name.' Student',
                'admission_no' => strtoupper($code),
            ]);

            $registration = FestRegistration::create([
                'event_id' => $event->id,
                'item_id' => $item->id,
                'school_id' => $school->id,
                'status' => 'approved',
            ]);
            FestParticipant::create([
                'registration_id' => $registration->id,
                'student_id' => $student->id,
                'participant_role' => 'main',
            ]);

            $levels[$code] = $batch;
            $items[$code] = $item;
        }

        // Level 1 invoiced, paid and approved. Level 2 left outstanding.
        app(FestRegistrationBatchFeeService::class)->recalculateAll($event->fresh(), $school->id);
        $feeOne = FestSchoolEventFee::where('event_id', $event->id)
            ->where('school_id', $school->id)
            ->where('registration_batch_id', $levels['level_1']->id)
            ->firstOrFail();
        FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $feeOne->id,
            'amount' => $feeOne->total_due,
            'status' => 'approved',
            'file_path' => 'fest-payments/level-1-proof.pdf',
        ]);
        $feeOne->refreshPaidState();

        return compact('sahodaya', 'school', 'admin', 'event', 'levels', 'items');
    }

    public function test_paid_level_gets_its_id_cards_while_another_level_is_unpaid(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->getJson(route('school.kalotsav.reports.id-cards.cards', [
            'tenantId' => $f['school']->id,
            'event' => $f['event']->id,
            'scope' => 'event',
            'batch_id' => $f['levels']['level_1']->id,
        ]));

        $response->assertOk();
        $this->assertFalse($response->json('downloadGate.blocked'));
        $this->assertCount(1, $response->json('cards'));
        $this->assertEquals('Level 1 Student', $response->json('cards.0.name'));
    }

    public function test_unpaid_level_is_still_blocked_and_names_the_level(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->getJson(route('school.kalotsav.reports.id-cards.cards', [
            'tenantId' => $f['school']->id,
            'event' => $f['event']->id,
            'scope' => 'event',
            'batch_id' => $f['levels']['level_2']->id,
        ]));

        $response->assertOk();
        $this->assertTrue($response->json('downloadGate.blocked'));
        $this->assertStringContainsString('Level 2', $response->json('downloadGate.reason'));
        $this->assertSame([], $response->json('cards'));
    }

    /** "All levels" is a genuine whole-event request, so it stays gated until every level is paid. */
    public function test_all_levels_selection_still_requires_every_level_to_be_paid(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->getJson(route('school.kalotsav.reports.id-cards.cards', [
            'tenantId' => $f['school']->id,
            'event' => $f['event']->id,
            'scope' => 'event',
            'batch_id' => 'all',
        ]));

        $response->assertOk();
        $this->assertTrue($response->json('downloadGate.blocked'));
        $this->assertSame([], $response->json('cards'));
    }

    public function test_page_defaults_to_the_paid_level_instead_of_reporting_the_whole_event_unpaid(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->get(route('school.kalotsav.reports.id-cards', [
            'tenantId' => $f['school']->id,
            'event' => $f['event']->id,
        ]));

        $response->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertEquals($f['levels']['level_1']->id, $props['defaultLevelId']);
        $this->assertFalse($props['downloadGate']['blocked']);
        $this->assertCount(2, $props['levels']);
        $this->assertTrue($props['levels'][0]['paid']);
        $this->assertFalse($props['levels'][1]['paid']);
    }
}
