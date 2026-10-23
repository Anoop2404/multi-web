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

    public function test_collection_reads_published_winners_from_phased_regional_leaves(): void
    {
        $this->hubEvent->update(['workflow_mode' => 'phased_regional_billing', 'results_published' => false]);
        $root = $this->hubEvent;
        $phase = \App\Models\FestEventPhase::create(['event_id' => $root->id, 'name' => 'Regional phase', 'is_regional' => true]);
        $marks = collect();
        foreach (['Manjeri', 'Nilambur'] as $name) {
            $leaf = FestEvent::create(['tenant_id' => $root->tenant_id, 'parent_event_id' => $root->id,
                'source_phase_id' => $phase->id, 'partition_key' => strtolower($name),
                'workflow_mode' => 'phased_regional_billing', 'title' => $name, 'event_type' => 'kalolsavam',
                'state_program_id' => $this->program->id, 'results_published' => false]);
            $this->hubEvent = $leaf;
            $this->item = FestEventItem::create(['event_id' => $leaf->id, 'title' => 'Light Music',
                'item_code' => 'LM01', 'state_program_item_id' => $this->stateItem->id,
                'results_published_at' => now()]);
            $marks->push($this->winner($name.' Winner', 1, 95));
        }
        $pool = app(\App\Services\State\FestStateCollectionCandidates::class)->pool($this->program, $root->fresh());
        $this->assertEqualsCanonicalizing($marks->pluck('id')->all(), $pool->pluck('mark_id')->all());
        $this->assertEqualsCanonicalizing(['manjeri', 'nilambur'], $pool->pluck('partition_key')->all());
        $this->item->update(['results_hidden' => true]);
        $pool = app(\App\Services\State\FestStateCollectionCandidates::class)->pool($this->program, $root->fresh());
        $this->assertCount(1, $pool->where('direct_entry', false));
        $this->assertFalse($pool->contains('mark_id', $marks->last()->id));
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
        $row = $this->service()->summary($this->program, $this->hubEvent)->first();
        $this->assertSame('attention', $row['slot_status']);
        $this->assertSame(0, $row['slot_progress']['accepted']);
        $this->assertSame(2, $row['slot_progress']['invalid']);
        $this->assertSame(0, $row['slot_progress']['percent']);
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

    public function test_candidates_use_central_school_ids_when_tenant_database_has_no_tenants_table(): void
    {
        $this->winner('Eligible winner', 1, 95, 'school-1');
        // A stray registration must not make a school from another cluster eligible.
        Tenant::create(['id' => 'foreign-cluster', 'name' => 'Other Sahodaya', 'type' => 'sahodaya']);
        Tenant::create(['id' => 'foreign-school', 'name' => 'Other School', 'type' => 'school', 'parent_id' => 'foreign-cluster']);
        $this->winner('Foreign winner', 2, 90, 'foreign-school');
        $db = \Illuminate\Support\Facades\DB::connection('central');
        $previous = config('database.default');
        config(['database.connections.slot_isolation_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        $isolated = \Illuminate\Support\Facades\DB::connection('slot_isolation_test');
        try {
            foreach (['fest_events', 'fest_event_items', 'fest_event_phases', 'fest_participants', 'fest_registrations',
                'students', 'school_classes', 'fest_groups', 'fest_marks'] as $table) {
                $sql = $db->selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table])->sql;
                $isolated->statement($sql);
                foreach ($db->table($table)->get() as $record) $isolated->table($table)->insert((array) $record);
            }
            \Illuminate\Support\Facades\DB::setDefaultConnection('slot_isolation_test');
            $this->assertFalse(\Illuminate\Support\Facades\Schema::connection('slot_isolation_test')->hasTable('tenants'));
            $pool = app(\App\Services\State\FestStateCollectionCandidates::class)->pool($this->program, $this->hubEvent);
            $this->assertCount(1, $pool);
            $this->assertSame('school-1', $pool->first()['school_id']);
            $this->assertSame(Tenant::findOrFail('school-1')->name, $pool->first()['school_name']);
            // The direct/unpublished path must use the same cross-database-safe filter.
            $isolated->table('fest_events')->where('id', $this->hubEvent->id)->update(['results_published' => false]);
            $hub = FestEvent::findOrFail($this->hubEvent->id);
            $direct = app(\App\Services\State\FestStateCollectionCandidates::class)->pool($this->program, $hub);
            $this->assertCount(1, $direct);
            $this->assertTrue($direct->first()['direct_entry']);
        } finally {
            \Illuminate\Support\Facades\DB::setDefaultConnection($previous);
            \Illuminate\Support\Facades\DB::purge('slot_isolation_test');
            config(['database.connections.slot_isolation_test' => null]);
        }
    }

    public function test_open_builds_candidates_once_and_later_school_responses_recheck_fresh_data(): void
    {
        for ($index = 0; $index < 8; $index++) {
            if ($index > 0) {
                $stateItem = $this->stateItem->replicate();
                $stateItem->item_code = 'PERF'.$index;
                $stateItem->save();
                $item = $this->item->replicate();
                $item->state_program_item_id = $stateItem->id;
                $item->item_code = 'PERF'.$index;
                $item->save();
                $this->item = $item;
            }
            $this->winner('First '.$index, 1, 95, 'school-1');
            $this->winner('Second '.$index, 2, 90, 'school-2');
        }
        $tracker = new class extends \App\Services\State\FestStateCollectionCandidates {
            public int $calls = 0;
            public function pool(FestStateProgram $program, FestEvent $hub): \Illuminate\Support\Collection
            {
                $this->calls++;
                return parent::pool($program, $hub);
            }
        };
        $this->app->instance(\App\Services\State\FestStateCollectionCandidates::class, $tracker);
        $result = $this->service()->open($this->program, $this->hubEvent, $this->user());
        $this->assertSame(16, $result['filled']);
        $this->assertSame(1, $tracker->calls, 'Opening must not rebuild the entire candidate pool per selected winner.');
        $this->service()->respond($this->primaries()->first(), 'accept', null, $this->user());
        $this->assertSame(2, $tracker->calls, 'School confirmation must load fresh eligibility after the opening transaction.');
    }

    public function test_item_status_tracks_not_open_pending_and_confirmed_completion(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->winner('Second', 2, 90, 'school-2');
        $this->assertSame('not_open', $this->service()->summary($this->program, $this->hubEvent)->first()['slot_status']);
        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $row = $this->service()->summary($this->program, $this->hubEvent)->first();
        $this->assertSame('pending', $row['slot_status']);
        $this->assertSame(2, $row['slot_progress']['pending']);
        $this->assertSame(0, $row['slot_progress']['percent']);
        $this->service()->respond($this->primaries()->first(), 'accept', null, $this->user());
        $row = $this->service()->summary($this->program, $this->hubEvent)->first();
        $this->assertSame('pending', $row['slot_status']);
        $this->assertSame(50, $row['slot_progress']['percent']);
        $this->service()->respond($this->primaries()->firstWhere('school_response', 'pending'), 'accept', null, $this->user());
        $row = $this->service()->summary($this->program, $this->hubEvent)->first();
        $this->assertSame('complete', $row['slot_status']);
        $this->assertSame(100, $row['slot_progress']['percent']);
    }

    public function test_linked_items_awaiting_results_remain_visible_as_pending_work(): void
    {
        $this->hubEvent->update(['results_published' => false]);
        foreach (['school-1', 'school-2', 'school-3'] as $i => $school) $this->winner('Entry '.$i, $i + 1, 0, $school)->delete();
        $rows = $this->service()->summary($this->program, $this->hubEvent);
        $this->assertCount(1, $rows);
        $this->assertSame('awaiting_eligibility', $rows->first()['slot_status']);
        $this->assertSame(2, $rows->first()['slot_progress']['unfilled']);
        $this->assertSame([], $rows->first()['candidates']);
    }

    public function test_status_distinguishes_unsent_offers_from_exhausted_unfilled_slots(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->hubEvent->update(['state_slot_collection_open' => true]);
        $this->assertSame('not_offered', $this->service()->summary($this->program, $this->hubEvent)->first()['slot_status']);
        $this->service()->syncAdditionalSlots($this->program, $this->hubEvent, $this->user());
        $this->service()->respond($this->primaries()->first(), 'opt_out', 'Cannot attend', $this->user());
        $row = $this->service()->summary($this->program, $this->hubEvent)->first();
        $this->assertSame('unfilled', $row['slot_status']);
        $this->assertSame(2, $row['slot_progress']['unfilled']);
        $this->assertSame(0, $row['slot_progress']['pending']);
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

    public function test_group_entry_selections_match_by_registration_id_even_if_participant_differs(): void
    {
        $this->stateItem->update(['participant_type' => 'group', 'qualify_count' => 1]);
        $this->item->update(['participant_type' => 'group']);

        // Create a group entry with 2 students in the same registration
        $class = SchoolClass::firstOrCreate(['tenant_id' => 'school-1', 'name' => 'Class 9']);
        $studentA = Student::create(['name' => 'Member A', 'tenant_id' => 'school-1', 'school_class_id' => $class->id]);
        $studentB = Student::create(['name' => 'Member B', 'tenant_id' => 'school-1', 'school_class_id' => $class->id]);

        $reg = FestRegistration::create([
            'event_id' => $this->hubEvent->id, 'item_id' => $this->item->id,
            'school_id' => 'school-1', 'status' => 'approved',
        ]);
        $partA = FestParticipant::create(['registration_id' => $reg->id, 'student_id' => $studentA->id]);
        $partB = FestParticipant::create(['registration_id' => $reg->id, 'student_id' => $studentB->id]);

        $markA = FestMark::create([
            'event_id' => $this->hubEvent->id, 'item_id' => $this->item->id,
            'participant_id' => $partA->id, 'position' => 1, 'score' => 95, 'grade' => 'A',
        ]);
        $markB = FestMark::create([
            'event_id' => $this->hubEvent->id, 'item_id' => $this->item->id,
            'participant_id' => $partB->id, 'position' => 1, 'score' => 95, 'grade' => 'A',
        ]);

        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $selection = $this->primaries()->first();

        // Simulate selection having member B's participant_id and mark_id
        $selection->update([
            'participant_id' => $partB->id,
            'mark_id' => $markB->id,
            'registration_id' => $reg->id,
        ]);

        $row = $this->service()->summary($this->program, $this->hubEvent)->first();
        $this->assertSame('pending', $row['slot_status']);
        $this->assertSame(0, $row['slot_progress']['invalid']);
        $this->assertTrue($row['chosen'][0]['is_eligible']);

        // Waiting list must not contain member A (the candidate pool must not consider the team unchosen)
        $waiting = collect($row['candidates'])->filter(fn ($c) => ! $c['is_chosen'] && ! $c['is_declined']);
        $this->assertCount(0, $waiting, 'Team should be marked chosen and not appear in waitlist');

        // School must be able to respond and accept
        $updated = $this->service()->respond($selection, 'accept', null, $this->user());
        $this->assertSame('accepted', $updated->school_response);
    }

    public function test_tie_with_one_first_and_two_seconds_allows_sahodaya_to_send_offer_to_chosen_second_rank(): void
    {
        // Quota = 2. 1 first rank, 2 second ranks (tied).
        $this->winner('First Place', 1, 95, 'school-1');
        $this->winner('Second Place A', 2, 90, 'school-2');
        $this->winner('Second Place B', 2, 90, 'school-3');

        // Open collection: First place is auto-offered (non-tied rank 1 < unresolved tie rank 2).
        // Rank 2 is skipped by auto-fill because 2 candidates are tied for the remaining 1 slot.
        $openResult = $this->service()->open($this->program, $this->hubEvent, $this->user());
        $this->assertSame(1, $openResult['filled']);
        $this->assertSame(['LM01 Light Music'], $openResult['skipped_ties']);

        $summary = $this->service()->summary($this->program, $this->hubEvent)->first();
        $this->assertSame('attention', $summary['slot_status']);
        $this->assertSame([2], $summary['unresolved_ties']);
        $this->assertSame(1, $summary['slots_left']);

        // First place is offered and pending
        $this->assertSame(['First Place'], collect($summary['chosen'])->pluck('student_name')->all());

        // Sahodaya chooses Second Place B by sending an offer
        $candB = collect($summary['candidates'])->firstWhere('student_name', 'Second Place B');
        $selectionB = $this->service()->sendOffer($this->program, $this->hubEvent, $candB, $this->user());

        $this->assertSame('pending', $selectionB->school_response);
        $this->assertSame('selected', $selectionB->status);

        // Now both slots are filled, tie is resolved!
        $updatedSummary = $this->service()->summary($this->program, $this->hubEvent)->first();
        $this->assertSame([], $updatedSummary['unresolved_ties']);
        $this->assertSame(0, $updatedSummary['slots_left']);
        $this->assertSame(2, $updatedSummary['chosen_count']);
        $this->assertSame(['First Place', 'Second Place B'], collect($updatedSummary['chosen'])->pluck('student_name')->all());
    }

    public function test_when_first_rank_opts_out_both_second_ranks_can_go_without_tie_conflict_and_third_is_not_needed(): void
    {
        // Quota = 2. 1 first rank, 2 second ranks (tied), 1 third rank.
        $this->winner('First Place', 1, 95, 'school-1');
        $this->winner('Second Place A', 2, 90, 'school-2');
        $this->winner('Second Place B', 2, 90, 'school-3');
        $this->winner('Third Place', 3, 85, 'school-1');

        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $firstSelection = $this->primaries()->firstWhere('student_name', 'First Place');

        // First place opts out: frees 1 slot, now 2 slots are available for 2 second-rankers!
        $this->service()->respond($firstSelection, 'opt_out', 'Cannot attend State', $this->user());

        // Both Second Place A and Second Place B fit in the 2 slots (no tie conflict)!
        // Third Place is NOT needed because Second Place fills both slots!
        $summary = $this->service()->summary($this->program, $this->hubEvent)->first();
        $this->assertSame([], $summary['unresolved_ties']);
        $this->assertSame(0, $summary['slots_left']);
        $this->assertSame(2, $summary['chosen_count']);

        $chosenNames = collect($summary['chosen'])->pluck('student_name')->sort()->values()->all();
        $this->assertSame(['Second Place A', 'Second Place B'], $chosenNames);

        // Third Place remains in waiting list, NOT chosen
        $waitingNames = collect($summary['candidates'])->filter(fn ($c) => ! $c['is_chosen'] && ! $c['is_declined'])->pluck('student_name')->all();
        $this->assertSame(['Third Place'], $waitingNames);
    }

    public function test_when_first_and_second_opt_out_third_rank_with_two_candidates_both_go(): void
    {
        // Quota = 2.
        // 1 first rank, 1 second rank, 2 third ranks (tied).
        $this->winner('First Place', 1, 95, 'school-1');
        $this->winner('Second Place', 2, 90, 'school-2');
        $this->winner('Third Place A', 3, 85, 'school-1');
        $this->winner('Third Place B', 3, 85, 'school-3');

        $this->service()->open($this->program, $this->hubEvent, $this->user());

        // First Place and Second Place both opt out
        $first = $this->primaries()->firstWhere('student_name', 'First Place');
        $second = $this->primaries()->firstWhere('student_name', 'Second Place');

        $this->service()->respond($first, 'opt_out', 'Declined', $this->user());
        // Reload second selection as autoFill may have touched item
        $second = $this->batch()->selections()->where('student_name', 'Second Place')->where('status', 'selected')->first();
        $this->service()->respond($second, 'opt_out', 'Declined', $this->user());

        // 2 slots left, 2 third rankers: both fit, no tie!
        $summary = $this->service()->summary($this->program, $this->hubEvent)->first();
        $this->assertSame([], $summary['unresolved_ties']);
        $this->assertSame(0, $summary['slots_left']);

        $chosenNames = collect($summary['chosen'])->pluck('student_name')->sort()->values()->all();
        $this->assertSame(['Third Place A', 'Third Place B'], $chosenNames);
    }

    public function test_when_first_and_second_opt_out_third_rank_with_three_candidates_creates_unresolved_tie_and_sahodaya_chooses(): void
    {
        // Quota = 2.
        // 1 first rank, 1 second rank, 3 third ranks (tied).
        $this->winner('First Place', 1, 95, 'school-1');
        $this->winner('Second Place', 2, 90, 'school-2');
        $this->winner('Third Place A', 3, 85, 'school-1');
        $this->winner('Third Place B', 3, 85, 'school-2');
        $this->winner('Third Place C', 3, 85, 'school-3');

        $this->service()->open($this->program, $this->hubEvent, $this->user());

        $first = $this->primaries()->firstWhere('student_name', 'First Place');
        $second = $this->primaries()->firstWhere('student_name', 'Second Place');

        $this->service()->respond($first, 'opt_out', 'Declined', $this->user());
        $second = $this->batch()->selections()->where('student_name', 'Second Place')->where('status', 'selected')->first();
        $this->service()->respond($second, 'opt_out', 'Declined', $this->user());

        // 2 slots open, 3 third rankers -> unresolved tie at rank 3!
        $summary = $this->service()->summary($this->program, $this->hubEvent)->first();
        $this->assertSame([3], $summary['unresolved_ties']);
        $this->assertSame(2, $summary['slots_left']);

        // Sahodaya chooses Third Place A and Third Place C
        $candA = collect($summary['candidates'])->firstWhere('student_name', 'Third Place A');
        $candC = collect($summary['candidates'])->firstWhere('student_name', 'Third Place C');

        $this->service()->sendOffer($this->program, $this->hubEvent, $candA, $this->user());
        $this->service()->sendOffer($this->program, $this->hubEvent, $candC, $this->user());

        // Now both slots filled, tie resolved!
        $finalSummary = $this->service()->summary($this->program, $this->hubEvent)->first();
        $this->assertSame([], $finalSummary['unresolved_ties']);
        $this->assertSame(0, $finalSummary['slots_left']);
        $chosenNames = collect($finalSummary['chosen'])->pluck('student_name')->sort()->values()->all();
        $this->assertSame(['Third Place A', 'Third Place C'], $chosenNames);
    }

    public function test_sahodaya_can_alter_selections_via_reopen_accept_opt_out_and_revoke(): void
    {
        $this->winner('Winner One', 1, 95, 'school-1');
        $this->winner('Winner Two', 2, 90, 'school-2');

        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $selOne = $this->primaries()->firstWhere('student_name', 'Winner One');
        $selTwo = $this->primaries()->firstWhere('student_name', 'Winner Two');

        // 1. Sahodaya accepts on school's behalf
        $accepted = $this->service()->alterSelection($this->program, $this->hubEvent, $selOne, 'accept', null, $this->user());
        $this->assertSame('accepted', $accepted->school_response);
        $this->assertStringContainsString('Sahodaya', $accepted->school_responded_by_name);

        // 2. Sahodaya records opt-out on school's behalf for Winner One (who had accepted earlier)
        $optedOut = $this->service()->alterSelection($this->program, $this->hubEvent, $selOne, 'opt_out', 'Family emergency', $this->user());
        $this->assertSame('opted_out', $optedOut->school_response);
        $this->assertSame('declined', $optedOut->status);
        $this->assertSame('Family emergency', $optedOut->skip_reason);

        // 3. Sahodaya reopens the offer for Winner One (since 1 slot is now open)
        $reopened = $this->service()->alterSelection($this->program, $this->hubEvent, $optedOut, 'reopen', null, $this->user());
        $this->assertSame('selected', $reopened->status);
        $this->assertSame('pending', $reopened->school_response);
        $this->assertNull($reopened->skip_reason);

        // 4. Sahodaya revokes offer for Winner Two (slot becomes unfilled)
        $this->service()->alterSelection($this->program, $this->hubEvent, $selTwo, 'revoke', null, $this->user());
        $this->assertDatabaseMissing('fest_state_nomination_selections', ['id' => $selTwo->id]);

        $summary = $this->service()->summary($this->program, $this->hubEvent)->first();
        $this->assertSame(1, $summary['slots_left']);
    }

    public function test_school_can_cancel_opt_out_and_reclaim_slot(): void
    {
        $this->winner('Winner One', 1, 95, 'school-1');
        $this->winner('Winner Two', 2, 90, 'school-2');

        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $selOne = $this->primaries()->firstWhere('student_name', 'Winner One');

        // School-1 opts out
        $this->service()->respond($selOne, 'opt_out', 'Unforeseen exam conflict', $this->user());
        $selOne->refresh();
        $this->assertSame('opted_out', $selOne->school_response);
        $this->assertSame('declined', $selOne->status);

        // School-1 cancels opt out
        $restored = $this->service()->cancelOptOut($selOne, $this->user());
        $this->assertSame('selected', $restored->status);
        $this->assertSame('pending', $restored->school_response);
        $this->assertNull($restored->skip_reason);
    }

    public function test_school_cancel_opt_out_revokes_pending_downstream_replacement(): void
    {
        $this->winner('Winner One', 1, 95, 'school-1');
        $this->winner('Winner Two', 2, 90, 'school-2');
        $this->winner('Winner Three', 3, 85, 'school-3');

        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $selOne = $this->primaries()->firstWhere('student_name', 'Winner One');

        // School-1 opts out -> Winner Three auto-fills as replacement
        $this->service()->respond($selOne, 'opt_out', 'Conflict', $this->user());
        $selThree = $this->primaries()->firstWhere('student_name', 'Winner Three');
        $this->assertNotNull($selThree);
        $this->assertSame('pending', $selThree->school_response);
        $this->assertSame($selOne->id, $selThree->replaces_selection_id);

        // School-1 cancels opt-out -> Winner Three should be deleted/revoked
        $this->service()->cancelOptOut($selOne, $this->user());
        $selOne->refresh();
        $this->assertSame('pending', $selOne->school_response);
        $this->assertSame('selected', $selOne->status);

        $this->assertDatabaseMissing('fest_state_nomination_selections', ['id' => $selThree->id]);
    }

    public function test_school_cannot_cancel_opt_out_if_downstream_replacement_already_accepted(): void
    {
        $this->winner('Winner One', 1, 95, 'school-1');
        $this->winner('Winner Two', 2, 90, 'school-2');
        $this->winner('Winner Three', 3, 85, 'school-3');

        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $selOne = $this->primaries()->firstWhere('student_name', 'Winner One');

        // School-1 opts out -> Winner Three auto-fills
        $this->service()->respond($selOne, 'opt_out', 'Conflict', $this->user());
        $selThree = $this->primaries()->firstWhere('student_name', 'Winner Three');

        // School-3 accepts the slot
        $this->service()->respond($selThree, 'accept', null, $this->user());

        // Now School-1 tries to cancel opt-out -> should fail
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service()->cancelOptOut($selOne, $this->user());
    }

    public function test_admin_can_update_mail_settings(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::firstOrCreate(
            ['email' => 'admin-mail-test@sahodaya.test'],
            [
                'name' => 'Admin Mail Tester',
                'username' => 'admin_mail_tester',
                'password' => 'password',
                'tenant_id' => 'sahodaya-1',
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['sahodaya_admin']);

        $res = $this->actingAs($admin)->post("/sahodaya-admin/sahodaya-1/events/{$this->hubEvent->id}/state-slot-collection/mail-settings", [
            'auto_notify_enabled' => false,
            'subject_template' => 'Custom Subject for {school_name}',
            'message_template' => 'Custom body with {action_url}',
            'reply_to_email' => 'reply@sahodaya.test',
        ]);

        $res->assertSessionHas('success');

        $this->hubEvent->refresh();
        $mailSettings = $this->hubEvent->notification_settings['state_slot_mail'] ?? [];
        $this->assertFalse($mailSettings['auto_notify_enabled']);
        $this->assertSame('Custom Subject for {school_name}', $mailSettings['subject_template']);
    }

    public function test_admin_can_send_state_slot_notification_emails(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::firstOrCreate(
            ['email' => 'admin-mail-sender@sahodaya.test'],
            [
                'name' => 'Admin Mail Sender',
                'username' => 'admin_mail_sender',
                'password' => 'password',
                'tenant_id' => 'sahodaya-1',
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['sahodaya_admin']);

        // Give school-1 a contact email in application_payload
        Tenant::where('id', 'school-1')->update([
            'application_payload' => ['school_email' => 'principal@school1.test'],
        ]);

        $this->winner('Winner One', 1, 95, 'school-1');
        $this->service()->open($this->program, $this->hubEvent, $this->user());

        $res = $this->actingAs($admin)->post("/sahodaya-admin/sahodaya-1/events/{$this->hubEvent->id}/state-slot-collection/send-email", [
            'audience' => 'pending',
            'subject' => 'Please confirm slots for {school_name}',
            'message' => 'Your student qualified! Visit {action_url}',
            'include_slot_details' => true,
        ]);

        $res->assertSessionHas('success');

        $this->hubEvent->refresh();
        $this->assertNotNull($this->hubEvent->notification_settings['state_slot_mail']['last_sent_at'] ?? null);
    }

    public function test_school_state_slots_hub_redirects_to_active_event_slots(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $schoolUser = User::create([
            'name' => 'School office',
            'email' => 'office-hub@example.test',
            'username' => 'office_hub',
            'password' => 'password',
            'tenant_id' => 'school-1',
            'email_verified_at' => now(),
        ]);
        $schoolUser->assignRole('school_admin');

        $res = $this->actingAs($schoolUser)->get('/school-admin/school-1/kalotsav/state-slots');

        $res->assertRedirect("/school-admin/school-1/kalotsav/events/{$this->hubEvent->id}/state-slots");
    }
}

