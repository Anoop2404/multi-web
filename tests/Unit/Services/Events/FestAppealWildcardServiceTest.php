<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestAppeal;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestResult;
use App\Models\FestStateProgram;
use App\Models\FestStateSubmissionOutbox;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\StateDomain;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\EventContext;
use App\Services\Events\FestAppealWildcardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A wildcard appeal grants a student a slot they didn't otherwise qualify for:
 * "sahodaya_wildcard" bypasses School-level selection into a Sahodaya-level
 * item, "state_wildcard" bypasses Sahodaya-level qualification into a
 * State-level item. See FestAppealWildcardService for the crediting rules.
 */
class FestAppealWildcardServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Wildcard Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'WC', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Origin School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1', 'participant_type' => 'individual', 'is_enabled' => true, 'results_published_at' => now()]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10', 'display_order' => 1, 'is_active' => true]);
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'admission_number' => 'ADM1', 'reg_no' => 'REG1', 'name' => 'Appeal Student', 'status' => 'active',
        ]);

        return compact('sahodaya', 'school', 'event', 'item', 'student');
    }

    private function pendingAppeal(FestEvent $event, FestEventItem $item, Student $student, string $type): FestAppeal
    {
        return FestAppeal::create([
            'event_id'    => $event->id,
            'appeal_type' => $type,
            'student_id'  => $student->id,
            'item_id'     => $item->id,
            'reason'      => 'Did not get a slot',
            'status'      => 'pending',
        ]);
    }

    public function test_granting_a_sahodaya_slot_files_the_registration_under_a_placeholder_school_but_keeps_the_origin_school(): void
    {
        ['school' => $school, 'event' => $event, 'item' => $item, 'student' => $student] = $this->fixture();

        $appeal = $this->pendingAppeal($event, $item, $student, FestAppeal::TYPE_SAHODAYA_WILDCARD);

        $registration = (new FestAppealWildcardService)->grantSahodayaSlot($appeal);

        $this->assertNotSame($school->id, $registration->school_id, 'must not be filed under the real school');
        $this->assertSame($student->tenant_id, $registration->origin_school_id, 'origin_school_id must record the real school');
        $this->assertSame('approved', $registration->status);
        $this->assertSame(1, $registration->participants->count());
        $this->assertSame($student->id, $registration->participants->first()->student_id);

        $placeholder = Tenant::find($registration->school_id);
        $this->assertNotNull($placeholder);
        $this->assertTrue((bool) $placeholder->is_appeal_pool);
        $this->assertSame($event->tenant_id, $placeholder->parent_id);

        $appeal->refresh();
        $this->assertSame($registration->id, $appeal->granted_registration_id);
    }

    public function test_a_second_wildcard_appeal_in_the_same_sahodaya_reuses_the_same_placeholder_school(): void
    {
        ['event' => $event, 'item' => $item, 'student' => $student] = $this->fixture();

        $service = new FestAppealWildcardService;

        $appeal1 = $this->pendingAppeal($event, $item, $student, FestAppeal::TYPE_SAHODAYA_WILDCARD);
        $reg1 = $service->grantSahodayaSlot($appeal1);

        $appeal2 = $this->pendingAppeal($event, $item, $student, FestAppeal::TYPE_SAHODAYA_WILDCARD);
        $reg2 = $service->grantSahodayaSlot($appeal2);

        $this->assertSame($reg1->school_id, $reg2->school_id);
        $this->assertSame(1, Tenant::where('is_appeal_pool', true)->count());
    }

    public function test_resolving_a_pending_sahodaya_wildcard_appeal_as_approved_grants_the_slot(): void
    {
        ['event' => $event, 'item' => $item, 'student' => $student] = $this->fixture();
        $appeal = $this->pendingAppeal($event, $item, $student, FestAppeal::TYPE_SAHODAYA_WILDCARD);

        (new FestAppealWildcardService)->resolve($appeal, 'approved', 'Looks fine', 999);

        $appeal->refresh();
        $this->assertSame('approved', $appeal->status);
        $this->assertNotNull($appeal->granted_registration_id);
        $this->assertSame(999, $appeal->resolved_by_user_id);
    }

    public function test_resolving_as_rejected_never_grants_a_slot(): void
    {
        ['event' => $event, 'item' => $item, 'student' => $student] = $this->fixture();
        $appeal = $this->pendingAppeal($event, $item, $student, FestAppeal::TYPE_SAHODAYA_WILDCARD);

        (new FestAppealWildcardService)->resolve($appeal, 'rejected', null, 999);

        $appeal->refresh();
        $this->assertSame('rejected', $appeal->status);
        $this->assertNull($appeal->granted_registration_id);
        $this->assertSame(0, FestRegistration::where('event_id', $event->id)->count());
    }

    /**
     * The whole point of filing an appeal entry under the placeholder school: its
     * points must never inflate a real school's championship total, even though
     * the item's own result still records the student normally (same FestMark row
     * a real registration would get).
     */
    public function test_the_placeholder_schools_points_are_excluded_from_the_real_school_championship_total(): void
    {
        ['school' => $realSchool, 'event' => $event, 'item' => $item, 'student' => $student] = $this->fixture();

        // A normal registration for a second student at the real school.
        $schoolClass = \App\Models\SchoolClass::where('tenant_id', $realSchool->id)->first();
        $normalStudent = Student::create([
            'tenant_id' => $realSchool->id, 'school_class_id' => $schoolClass->id,
            'admission_number' => 'ADM2', 'reg_no' => 'REG2', 'name' => 'Normal Student', 'status' => 'active',
        ]);
        $normalRegistration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $realSchool->id, 'status' => 'approved']);
        $normalParticipant = FestParticipant::create(['registration_id' => $normalRegistration->id, 'event_id' => $event->id, 'student_id' => $normalStudent->id, 'participant_type' => 'student']);
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $normalParticipant->id, 'grade' => 'A', 'position' => 1]);

        // The wildcard appeal entry, filed under the placeholder school.
        $appeal = $this->pendingAppeal($event, $item, $student, FestAppeal::TYPE_SAHODAYA_WILDCARD);
        $registration = (new FestAppealWildcardService)->grantSahodayaSlot($appeal);
        $participant = $registration->participants->first();
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'grade' => 'A', 'position' => 2]);

        EventContext::for($event->fresh())->recalculateSchoolPoints();

        // Default table: grade A, position 1 => 8 points (per FestPointRuleRecalculationTest).
        $this->assertDatabaseHas('fest_results', [
            'event_id' => $event->id, 'item_id' => null, 'school_id' => $realSchool->id, 'total_points' => 8,
        ]);
        $this->assertDatabaseMissing('fest_results', [
            'event_id' => $event->id, 'item_id' => null, 'school_id' => $registration->school_id,
        ]);
        $this->assertSame(1, FestResult::where('event_id', $event->id)->whereNull('item_id')->count(), 'only the real school should have an overall result row');
    }

    public function test_granting_a_state_slot_submits_a_wildcard_qualifier_entry_and_credits_the_real_school(): void
    {
        ['school' => $school, 'event' => $event, 'item' => $item, 'student' => $student] = $this->fixture();

        $domain = StateDomain::create([
            'tenant_id' => $event->tenant_id, 'name' => 'Test State', 'domain' => 'state.test',
            'api_base_url' => 'https://state.test', 'status' => 'active',
        ]);
        $program = FestStateProgram::create([
            'title' => 'State Kalotsav', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state', 'sahodaya', 'school'],
            'status' => 'active', 'state_domain_id' => $domain->id,
        ]);
        $event->update(['state_program_id' => $program->id]);
        $item->update(['state_program_item_id' => (string) Str::uuid()]);

        Http::fake(['*' => Http::response(['intake_id' => 'intake-123'], 200)]);

        $appeal = $this->pendingAppeal($event->fresh(), $item->fresh(), $student, FestAppeal::TYPE_STATE_WILDCARD);
        $outbox = (new FestAppealWildcardService)->grantStateSlot($appeal, 42);

        $this->assertSame('qualifier_wildcard', $outbox->submission_type);
        $this->assertSame('completed', $outbox->status);
        $this->assertSame($school->id, $outbox->payload['entries'][0]['school_id'], 'the real origin school must be credited, not a placeholder');
        $this->assertSame('wildcard_appeal', $outbox->payload['entries'][0]['qualifier_type']);

        $appeal->refresh();
        $this->assertSame($outbox->id, $appeal->granted_state_reference);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/state/qualifiers/intake'));
    }
}
