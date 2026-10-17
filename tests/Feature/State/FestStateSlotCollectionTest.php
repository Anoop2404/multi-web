<?php

namespace Tests\Feature\State;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestStateNominationSelection;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\State\FestStateNominationService;
use App\Services\State\FestStateSlotCollectionService;
use App\Services\State\FestStateWinnerSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * "Get a confirmed list before State registration opens" — a phase gate on top of the existing
 * State winner sheet. See App\Services\State\FestStateSlotCollectionService.
 */
class FestStateSlotCollectionTest extends TestCase
{
    use RefreshDatabase;

    private FestStateProgram $program;

    private FestEvent $hubEvent;

    private FestEventItem $item;

    private FestStateProgramItem $stateItem;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');

        Tenant::create(['id' => 'sahodaya-1', 'name' => 'Sahodaya One', 'type' => 'sahodaya']);
        Tenant::create(['id' => 'school-1', 'name' => 'School One', 'type' => 'school', 'parent_id' => 'sahodaya-1']);
        Tenant::create(['id' => 'school-2', 'name' => 'School Two', 'type' => 'school', 'parent_id' => 'sahodaya-1']);
        Tenant::create(['id' => 'school-3', 'name' => 'School Three', 'type' => 'school', 'parent_id' => 'sahodaya-1']);

        $this->program = FestStateProgram::create([
            'title' => 'Kerala State Kalotsavam 2026', 'event_type' => 'kalolsavam',
            'conduct_levels' => ['sahodaya', 'state'], 'status' => 'published',
        ]);

        $this->stateItem = FestStateProgramItem::create([
            'state_program_id' => $this->program->id, 'title' => 'Light Music',
            'item_code' => 'LM01', 'category' => 'music', 'class_group' => 'category_2',
            'qualify_count' => 2, 'participant_type' => 'individual',
        ]);

        $this->hubEvent = FestEvent::create([
            'tenant_id' => 'sahodaya-1', 'title' => 'Sahodaya Kalotsavam 2026',
            'event_type' => 'kalolsavam', 'level_round' => 'sahodaya',
            'state_program_id' => $this->program->id,
            'results_published' => true, 'status' => 'published',
        ]);

        $this->item = FestEventItem::create([
            'event_id' => $this->hubEvent->id, 'state_program_item_id' => $this->stateItem->id,
            'title' => 'Light Music', 'category' => 'music', 'item_code' => 'LM01',
        ]);
    }

    private function winner(string $name, int $position, float $score, string $schoolId = 'school-1'): FestMark
    {
        $class = SchoolClass::firstOrCreate(['tenant_id' => $schoolId, 'name' => 'Class 6']);
        $student = Student::create(['name' => $name, 'tenant_id' => $schoolId, 'school_class_id' => $class->id]);

        $registration = FestRegistration::create([
            'event_id' => $this->hubEvent->id, 'item_id' => $this->item->id,
            'school_id' => $schoolId, 'status' => 'approved',
        ]);

        $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id]);

        return FestMark::create([
            'event_id' => $this->hubEvent->id, 'item_id' => $this->item->id,
            'participant_id' => $participant->id, 'position' => $position,
            'score' => $score, 'grade' => 'A',
        ]);
    }

    private function service(): FestStateSlotCollectionService
    {
        return app(FestStateSlotCollectionService::class);
    }

    private function batch()
    {
        return app(FestStateNominationService::class)->openBatch($this->program, $this->hubEvent);
    }

    private function primaries()
    {
        return $this->batch()->selections()->where('nomination_type', 'primary')->where('status', 'selected')->get();
    }

    private array $users = [];

    private function user(string $name = 'Convener'): User
    {
        return $this->users[$name] ??= User::create([
            'name' => $name, 'email' => \Str::slug($name).'@example.test', 'username' => \Str::slug($name),
            'password' => 'password', 'tenant_id' => 'sahodaya-1', 'email_verified_at' => now(),
        ]);
    }

    public function test_overview_uses_the_shared_public_url_without_an_undefined_property(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = $this->user('Admin');
        $admin->assignRole('sahodaya_admin');
        $this->actingAs($admin)->get('/sahodaya-admin/sahodaya-1/state-slot-collection')
            ->assertOk()->assertInertia(fn ($page) => $page->component('Sahodaya/Events/StateSlotCollectionOverview', false)
                ->has('publicUrl')->has('events', 1));
    }

    public function test_event_collection_page_uses_the_shared_public_url(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = $this->user('Admin');
        $admin->assignRole('sahodaya_admin');
        $this->actingAs($admin)->get("/sahodaya-admin/sahodaya-1/events/{$this->hubEvent->id}/state-slot-collection")
            ->assertOk()->assertInertia(fn ($page) => $page->component('Sahodaya/Events/StateSlotCollection', false)
                ->has('publicUrl')->where('event.id', $this->hubEvent->id));
    }

    public function test_unpublished_items_with_one_or_two_entries_get_direct_school_offers_without_results(): void
    {
        $this->hubEvent->update(['results_published' => false]);
        $this->winner('Direct one', 1, 0, 'school-1')->delete();
        $this->winner('Direct two', 2, 0, 'school-2')->delete();
        $result = $this->service()->open($this->program, $this->hubEvent, $this->user());
        $this->assertSame(2, $result['filled']);
        $this->assertSame(0, FestMark::count());
        $this->assertSame([null, null], $this->primaries()->pluck('source_position')->all());
        $slots = $this->service()->forSchool($this->program, $this->hubEvent, 'school-1');
        $this->assertCount(1, $slots);
        $this->assertTrue($slots[0]['direct_entry']);
        $this->assertTrue($slots[0]['can_respond']);
        foreach ($this->primaries() as $selection) $this->service()->respond($selection, 'accept', null, $this->user());
        $this->assertSame(2, $this->service()->approve($this->program, $this->hubEvent, $this->user())['approved_count']);
    }

    public function test_three_entries_without_published_results_are_not_directly_offered(): void
    {
        $this->hubEvent->update(['results_published' => false]);
        foreach (['school-1', 'school-2', 'school-3'] as $i => $school) $this->winner('Entry '.$i, $i + 1, 0, $school)->delete();
        $this->assertSame(0, $this->service()->open($this->program, $this->hubEvent, $this->user())['filled']);
        $this->assertCount(0, $this->primaries());
        $this->assertSame([], $this->service()->forSchool($this->program, $this->hubEvent, 'school-1'));
    }

    public function test_group_members_are_one_direct_competitive_entry(): void
    {
        $this->hubEvent->update(['results_published' => false]);
        $this->item->update(['participant_type' => 'group']);
        $mark = $this->winner('Team leader', 1, 0);
        $registration = $mark->participant->registration;
        $mark->delete();
        for ($i = 0; $i < 4; $i++) {
            $student = Student::create(['name' => 'Team member '.$i, 'tenant_id' => 'school-1', 'school_class_id' => $registration->participants()->first()->student->school_class_id]);
            FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id]);
        }
        $this->assertSame(1, $this->service()->open($this->program, $this->hubEvent, $this->user())['filled']);
        $this->assertCount(1, $this->primaries());
    }

    public function test_rank_three_to_five_wait_and_promote_in_order_but_rank_six_never_appears(): void
    {
        for ($rank = 1; $rank <= 6; $rank++) $this->winner('Rank '.$rank, $rank, 100 - $rank, $rank < 3 ? 'school-'.$rank : 'school-3');
        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $this->assertSame([1, 2], $this->primaries()->pluck('source_position')->map(fn ($v) => (int) $v)->sort()->values()->all());
        $waiting = $this->service()->forSchool($this->program, $this->hubEvent, 'school-3');
        $this->assertCount(3, $waiting);
        $this->assertSame([3, 4, 5], array_column($waiting, 'source_position'));
        $this->assertSame([false, false, false], array_column($waiting, 'can_respond'));
        $this->assertSame(['waiting', 'waiting', 'waiting'], array_column($waiting, 'school_response'));
        foreach ([1, 3, 4, 5] as $rank) {
            $current = $this->primaries()->firstWhere('student_name', 'Rank '.$rank);
            $this->assertNotNull($current);
            $this->service()->respond($current, 'opt_out', 'Cannot attend', $this->user());
        }
        $this->assertSame(['Rank 2'], $this->primaries()->pluck('student_name')->all());
    }

    public function test_collection_excludes_non_state_quota_items_and_shows_category_gender_labels(): void
    {
        $this->stateItem->update(['gender' => 'female']);
        $this->winner('Eligible', 1, 90);
        $row = $this->service()->summary($this->program, $this->hubEvent)->first();
        $this->assertSame('Girls', $row['gender_label']);
        $this->assertNotSame('category_2', $row['category_label']);
        $this->stateItem->update(['qualify_count' => 0]);
        $this->assertCount(0, $this->service()->summary($this->program, $this->hubEvent));
    }

    public function test_shared_school_link_requires_login_and_routes_only_the_correct_school(): void
    {
        $tenant = Tenant::findOrFail('sahodaya-1');
        $tenant->update(['domain' => 'slot-school.test']);
        $url = "http://slot-school.test/state-slot-collection/{$this->hubEvent->id}";
        $this->get($url)->assertRedirect('/school-login')->assertSessionHas('url.intended', $url);
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $schoolUser = User::create(['name' => 'School office', 'email' => 'office@example.test', 'username' => 'office', 'password' => 'password', 'tenant_id' => 'school-1', 'email_verified_at' => now()]);
        $schoolUser->assignRole('school_admin');
        $this->post('http://slot-school.test/login', ['email' => 'office', 'password' => 'password'])->assertRedirect($url);
        $this->get($url)->assertRedirect('/school-admin/school-1/kalotsav/events/'.$this->hubEvent->id.'/state-slots');
        $this->get('http://slot-school.test/school-admin/school-1/kalotsav/events/'.$this->hubEvent->id.'/state-slots')->assertOk();
        $otherSchool = $this->get('http://slot-school.test/school-admin/school-2/kalotsav/events/'.$this->hubEvent->id.'/state-slots');
        $this->assertSame(403, $otherSchool->getStatusCode(), (string) $otherSchool->headers->get('Location'));
        $schoolUser->update(['tenant_id' => 'sahodaya-1']);
        $this->actingAs($schoolUser->fresh())->get($url)->assertForbidden();
    }

    public function test_a_third_late_entry_blocks_confirmation_of_an_old_direct_offer(): void
    {
        $this->hubEvent->update(['results_published' => false]);
        $this->winner('Direct one', 1, 0, 'school-1')->delete();
        $this->winner('Direct two', 2, 0, 'school-2')->delete();
        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $selection = $this->primaries()->first();
        $this->winner('Late third', 3, 0, 'school-3')->delete();
        $this->assertFalse($this->service()->forSchool($this->program, $this->hubEvent, 'school-1')[0]['can_respond']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->respond($selection, 'accept', null, $this->user());
    }

    public function test_approval_rechecks_direct_eligibility_after_school_acceptances(): void
    {
        $this->hubEvent->update(['results_published' => false]);
        $this->winner('Direct one', 1, 0, 'school-1')->delete();
        $this->winner('Direct two', 2, 0, 'school-2')->delete();
        $this->service()->open($this->program, $this->hubEvent, $this->user());
        foreach ($this->primaries() as $selection) $this->service()->respond($selection, 'accept', null, $this->user());
        $this->winner('Late third', 3, 0, 'school-3')->delete();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->approve($this->program, $this->hubEvent, $this->user());
    }

    public function test_a_lower_rank_is_not_initially_offered_when_top_two_are_missing(): void
    {
        $this->winner('Only third', 3, 80, 'school-3');
        $this->assertSame(0, $this->service()->open($this->program, $this->hubEvent, $this->user())['filled']);
        $slots = $this->service()->forSchool($this->program, $this->hubEvent, 'school-3');
        $this->assertCount(1, $slots);
        $this->assertFalse($slots[0]['can_respond']);
    }

    public function test_school_response_cannot_change_after_final_approval(): void
    {
        $this->winner('Winner', 1, 95);
        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $selection = $this->primaries()->first();
        $this->service()->respond($selection, 'accept', null, $this->user());
        $this->service()->approve($this->program, $this->hubEvent, $this->user());
        $selection->update(['school_response' => 'pending']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->respond($selection, 'opt_out', 'No longer available', $this->user());
    }

    public function test_an_opt_out_replaces_only_an_existing_offer_when_a_top_rank_is_missing(): void
    {
        $this->winner('Second', 2, 90, 'school-1');
        $this->winner('Third', 3, 80, 'school-2');
        $this->winner('Fourth', 4, 70, 'school-3');
        $this->assertSame(1, $this->service()->open($this->program, $this->hubEvent, $this->user())['filled']);
        $this->service()->respond($this->primaries()->first(), 'opt_out', 'Cannot attend', $this->user());
        $this->assertSame(['Third'], $this->primaries()->pluck('student_name')->all());
    }

    public function test_opening_fills_the_top_ranks_and_marks_them_pending(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->winner('Second', 2, 88, 'school-2');
        $this->winner('Third', 3, 80, 'school-1');

        $result = $this->service()->open($this->program, $this->hubEvent, $this->user());

        $this->assertSame(2, $result['filled']);
        $this->hubEvent->refresh();
        $this->assertTrue($this->hubEvent->state_slot_collection_open);
        $this->assertNotNull($this->hubEvent->state_slot_collection_opened_at);

        $primaries = $this->primaries();
        $this->assertCount(2, $primaries);
        $this->assertSame(['pending', 'pending'], $primaries->pluck('school_response')->all());
        $this->assertSame(['First', 'Second'], $primaries->pluck('student_name')->sort()->values()->all());
    }

    public function test_opening_twice_is_refused(): void
    {
        $this->winner('First', 1, 95);
        $this->service()->open($this->program, $this->hubEvent, $this->user());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->open($this->program, $this->hubEvent, $this->user());
    }

    public function test_a_school_accepting_records_who_and_when(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->service()->open($this->program, $this->hubEvent, $this->user());

        $selection = $this->primaries()->first();
        $by = $this->user('School Coordinator');

        $updated = $this->service()->respond($selection, 'accept', null, $by);

        $this->assertSame('accepted', $updated->school_response);
        $this->assertSame($by->id, $updated->school_responded_by);
        $this->assertNotNull($updated->school_responded_at);
        $this->assertSame('selected', $updated->status);
    }

    public function test_opting_out_requires_a_reason(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->service()->open($this->program, $this->hubEvent, $this->user());

        $selection = $this->primaries()->first();

        $this->expectException(ValidationException::class);
        $this->service()->respond($selection, 'opt_out', '', $this->user());
    }

    public function test_opting_out_declines_and_offers_the_next_rank(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->winner('Second', 2, 88, 'school-2');
        $this->winner('Third', 3, 80, 'school-3');

        $this->service()->open($this->program, $this->hubEvent, $this->user());

        $first = $this->primaries()->firstWhere('student_name', 'First');
        $this->service()->respond($first, 'opt_out', 'Exam clash', $this->user());

        $first->refresh();
        $this->assertSame(FestStateWinnerSheetService::DECLINED, $first->status);
        $this->assertSame('opted_out', $first->school_response);
        $this->assertSame('Exam clash', $first->skip_reason);

        $primaries = $this->primaries();
        $this->assertCount(2, $primaries);
        $names = $primaries->pluck('student_name')->sort()->values()->all();
        $this->assertSame(['Second', 'Third'], $names);

        $promoted = $primaries->firstWhere('student_name', 'Third');
        $this->assertSame('pending', $promoted->school_response);
        $this->assertSame($first->id, $promoted->replaces_selection_id);
    }

    public function test_opting_out_with_nobody_left_just_shrinks_the_slot(): void
    {
        $this->winner('First', 1, 95, 'school-1');

        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $first = $this->primaries()->first();
        $this->service()->respond($first, 'opt_out', 'Exam clash', $this->user());

        $this->assertCount(0, $this->primaries());
    }

    public function test_responding_twice_to_the_same_offer_is_refused(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $selection = $this->primaries()->first();

        $this->service()->respond($selection, 'accept', null, $this->user());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->respond($selection->fresh(), 'accept', null, $this->user());
    }

    public function test_approve_is_blocked_while_a_slot_is_still_pending(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->winner('Second', 2, 88, 'school-2');
        $this->service()->open($this->program, $this->hubEvent, $this->user());

        $first = $this->primaries()->firstWhere('student_name', 'First');
        $this->service()->respond($first, 'accept', null, $this->user());
        // Second never responds.

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->approve($this->program, $this->hubEvent, $this->user());
    }

    public function test_approve_succeeds_once_every_offer_is_answered(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->winner('Second', 2, 88, 'school-2');
        $this->service()->open($this->program, $this->hubEvent, $this->user());

        foreach ($this->primaries() as $selection) {
            $this->service()->respond($selection, 'accept', null, $this->user());
        }

        $result = $this->service()->approve($this->program, $this->hubEvent, $this->user());

        $this->assertSame(2, $result['approved_count']);
        $this->hubEvent->refresh();
        $this->assertNotNull($this->hubEvent->state_slot_collection_approved_at);
    }

    public function test_approve_is_blocked_after_an_opt_out_chain_leaves_no_one_and_it_is_never_offered_again(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        // Only one candidate, quota is 2 — one slot will always be short, and that is a
        // warning, not a blocker (matches readiness() on the existing winner sheet).
        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $first = $this->primaries()->first();
        $this->service()->respond($first, 'accept', null, $this->user());

        $result = $this->service()->approve($this->program, $this->hubEvent, $this->user());
        $this->assertSame(1, $result['approved_count']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_responding_after_the_event_is_certified_is_refused(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $selection = $this->primaries()->first();

        $batch = $this->batch();
        $batch->update(['status' => 'certified', 'certified_at' => now()]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->respond($selection, 'accept', null, $this->user());
    }

    public function test_a_hand_picked_selection_outside_collection_is_never_marked_pending(): void
    {
        $mark = $this->winner('Hand Picked', 1, 95, 'school-1');
        $nominations = app(FestStateNominationService::class);
        $batch = $this->batch();

        $nominations->select($batch, [
            'item_id' => $this->stateItem->id, 'item_code' => 'LM01', 'item_title' => 'Light Music',
            'mark_id' => $mark->id, 'school_id' => 'school-1', 'school_name' => 'School One',
            'student_name' => 'Hand Picked', 'source_position' => 1,
        ], 'primary', 1, $this->user());

        $selection = FestStateNominationSelection::where('mark_id', $mark->id)->first();
        $this->assertNull($selection->school_response);

        // Collection was never opened for this event, so the register-flow guard must not engage.
        $this->hubEvent->refresh();
        $this->assertFalse($this->hubEvent->state_slot_collection_open);
    }
}
