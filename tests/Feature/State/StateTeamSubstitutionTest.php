<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateSahodaya;
use App\Models\State\StateSubstitution;
use App\Services\State\Fest\StateEventSettings;
use App\Services\State\Fest\StateTeamService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 4 of the State Kalotsav module — teams, squads and substitutions.
 *
 * A team entry consumes one slot whatever its size; standbys travel without competing; and a
 * substitution is a decision with a reason and an approver rather than an edit.
 */
class StateTeamSubstitutionTest extends TestCase
{
    use RefreshDatabase;

    private PlatformState $state;

    private StateFestEvent $event;

    private FestStateProgramItem $item;

    private StateFestRegistration $registration;

    private StateSahodaya $sahodaya;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Artisan::call('state:migrate');

        $this->state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);
        $program = FestStateProgram::create([
            'title' => 'Kerala State Kalotsavam 2026', 'state_id' => $this->state->id,
            'event_type' => 'kalolsavam', 'conduct_levels' => ['state'], 'status' => 'published',
        ]);
        $this->event = StateFestEvent::create([
            'state_program_id' => $program->id, 'state_id' => $this->state->id,
            'name' => 'State Finals', 'slug' => 'finals', 'status' => 'active',
        ]);
        $this->item = FestStateProgramItem::create([
            'state_program_id' => $program->id, 'title' => 'Group Dance', 'item_code' => 'GD01',
            'participant_type' => 'group', 'qualify_count' => 2, 'min_group_size' => 4, 'max_group_size' => 6,
        ]);
        $this->sahodaya = StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->state->id, 'name' => 'Malappuram Sahodaya',
            'tenant_id' => (string) Str::uuid(), 'origin' => StateSahodaya::ORIGIN_MANAGED,
        ]);

        $this->registration = StateFestRegistration::create([
            'state_event_id' => $this->event->id, 'sahodaya_id' => $this->sahodaya->id,
            'sahodaya_name' => 'Malappuram Sahodaya', 'school_id' => 'sjh', 'school_name' => 'St Joseph HSS',
            'item_id' => $this->item->id, 'item_code' => 'GD01', 'status' => 'approved',
        ]);
    }

    private function member(string $name, array $attrs = []): StateFestParticipant
    {
        return StateFestParticipant::create(array_merge([
            'state_event_id' => $this->event->id, 'registration_id' => $this->registration->id,
            'student_name' => $name, 'class_name' => 'Class 10',
        ], $attrs));
    }

    private function teams(): StateTeamService
    {
        return app(StateTeamService::class);
    }

    private array $users = [];

    private function user(string $role = 'state_admin'): PlatformUser
    {
        if (isset($this->users[$role])) {
            return $this->users[$role];
        }

        $user = PlatformUser::create([
            'name' => 'Registrar', 'email' => $role.'@example.test', 'username' => $role,
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $this->users[$role] = $user;
    }

    public function test_a_team_is_listed_with_its_sahodaya_and_school(): void
    {
        $this->member('A');
        $this->member('B');

        $team = $this->teams()->teamsFor($this->event)->sole();

        $this->assertSame('Malappuram Sahodaya', $team['sahodaya']);
        $this->assertSame('St Joseph HSS', $team['school'], 'The School a team came from is never dropped.');
        $this->assertSame(2, $team['competing']);
    }

    public function test_standbys_do_not_count_toward_the_team_size(): void
    {
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $this->member($name);
        }
        $this->member('Standby', ['is_standby' => true]);

        $team = $this->teams()->teamsFor($this->event)->sole();

        $this->assertSame(4, $team['competing'], 'Four competing meets the minimum.');
        $this->assertSame(1, $team['standbys']);
        $this->assertNull($team['size_problem']);
    }

    public function test_a_team_below_the_minimum_is_flagged(): void
    {
        $this->member('A');
        $this->member('B');

        $team = $this->teams()->teamsFor($this->event)->sole();

        $this->assertSame('Needs 4; has 2 competing.', $team['size_problem']);
    }

    public function test_naming_a_leader_stands_the_previous_one_down(): void
    {
        $first = $this->member('A', ['is_leader' => true]);
        $second = $this->member('B');

        $this->teams()->setLeader($this->registration, $second->id);

        $this->assertFalse($first->fresh()->is_leader);
        $this->assertTrue($second->fresh()->is_leader);
    }

    public function test_a_standby_cannot_lead_and_a_leader_cannot_be_made_standby(): void
    {
        $standby = $this->member('Standby', ['is_standby' => true]);
        $leader = $this->member('Leader', ['is_leader' => true]);

        try {
            $this->teams()->setLeader($this->registration, $standby->id);
            $this->fail('A standby must not lead the team.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('standby cannot lead', collect($e->errors())->flatten()->first());
        }

        $this->expectException(ValidationException::class);
        $this->teams()->setStandby($this->registration, $leader->id, true);
    }

    public function test_a_substitution_is_requested_not_applied(): void
    {
        $original = $this->member('Injured Student');

        $substitution = $this->teams()->requestSubstitution($this->event, $this->registration, [
            'original_participant_id' => $original->id,
            'substitute_name' => 'Replacement Student',
            'reason' => 'Ankle injury, certificate attached',
            'user_name' => 'Coordinator',
        ]);

        $this->assertSame('requested', $substitution->status);
        // Nothing has changed on the team yet — a State officer decides.
        $this->assertNull($original->fresh()->withdrawn_at);
        $this->assertSame(1, $this->registration->participants()->count());
    }

    public function test_approving_withdraws_the_original_and_adds_the_substitute(): void
    {
        $original = $this->member('Injured Student', ['chest_number' => '204', 'is_leader' => true]);

        $substitution = $this->teams()->requestSubstitution($this->event, $this->registration, [
            'original_participant_id' => $original->id,
            'substitute_name' => 'Replacement Student',
            'reason' => 'Injury',
        ]);

        $this->teams()->approveSubstitution($substitution, ['user_name' => 'Registrar']);

        // Withdrawn, not deleted: the record must show who was replaced by whom.
        $this->assertNotNull($original->fresh()->withdrawn_at);

        $substitute = $this->registration->participants()->where('student_name', 'Replacement Student')->sole();
        $this->assertSame('204', $substitute->chest_number, 'The chest number is already printed on sheets.');
        $this->assertTrue($substitute->is_leader, 'The substitute inherits the role being vacated.');
        $this->assertSame('Injured Student', $substitute->meta['substituted_for']);
    }

    public function test_a_rejected_substitution_changes_nothing_and_must_say_why(): void
    {
        $original = $this->member('Student');
        $substitution = $this->teams()->requestSubstitution($this->event, $this->registration, [
            'original_participant_id' => $original->id,
            'substitute_name' => 'Other Student',
            'reason' => 'Injury',
        ]);

        try {
            $this->teams()->rejectSubstitution($substitution, []);
            $this->fail('A refusal must say why — the Sahodaya sees it.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Say why', collect($e->errors())->flatten()->first());
        }

        $this->teams()->rejectSubstitution($substitution, ['note' => 'No medical evidence supplied.']);

        $this->assertSame('rejected', $substitution->fresh()->status);
        $this->assertNull($original->fresh()->withdrawn_at);
        $this->assertSame(1, $this->registration->participants()->count());
    }

    public function test_a_substitution_cannot_be_decided_twice(): void
    {
        $original = $this->member('Student');
        $substitution = $this->teams()->requestSubstitution($this->event, $this->registration, [
            'original_participant_id' => $original->id, 'substitute_name' => 'Other', 'reason' => 'Injury',
        ]);

        $this->teams()->approveSubstitution($substitution);

        $this->expectException(ValidationException::class);
        $this->teams()->approveSubstitution($substitution->fresh());
    }

    public function test_a_substitute_already_in_the_team_is_refused(): void
    {
        // That is a duplicate, not a replacement.
        $original = $this->member('Student A');
        $this->member('Student B');

        $this->expectException(ValidationException::class);
        $this->teams()->requestSubstitution($this->event, $this->registration, [
            'original_participant_id' => $original->id, 'substitute_name' => 'Student B', 'reason' => 'Injury',
        ]);
    }

    public function test_the_same_participant_cannot_be_substituted_twice(): void
    {
        $original = $this->member('Student');
        $first = $this->teams()->requestSubstitution($this->event, $this->registration, [
            'original_participant_id' => $original->id, 'substitute_name' => 'Replacement One', 'reason' => 'Injury',
        ]);
        $this->teams()->approveSubstitution($first);

        $this->expectException(ValidationException::class);
        $this->teams()->requestSubstitution($this->event, $this->registration, [
            'original_participant_id' => $original->id, 'substitute_name' => 'Replacement Two', 'reason' => 'Injury again',
        ]);
    }

    public function test_substitutions_close_with_the_scrutiny_window(): void
    {
        // After the State has finished deciding who competes, a change of participant is an appeal.
        app(StateEventSettings::class)->update($this->event, ['scrutiny_closes_at' => now()->subDay()->toDateTimeString()]);
        $original = $this->member('Student');

        try {
            $this->teams()->requestSubstitution($this->event->fresh(), $this->registration, [
                'original_participant_id' => $original->id, 'substitute_name' => 'Late', 'reason' => 'Injury',
            ]);
            $this->fail('A closed window must refuse substitutions.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('close with scrutiny', collect($e->errors())->flatten()->first());
        }
    }

    public function test_the_screens_render_and_are_gated(): void
    {
        $this->member('A');
        $this->member('B');
        $url = "http://superadmin.test/admin/state/fest/{$this->event->id}";

        $page = $this->actingAs($this->user(), 'platform')->get("{$url}/teams")->assertOk()->viewData('page');
        $this->assertSame('State/Fest/Teams', $page['component']);
        $this->assertCount(1, $page['props']['teams']);
        $this->assertSame(1, $page['props']['problems'], 'A two-member team on a four-minimum item is flagged.');

        $this->actingAs($this->user(), 'platform')->get("{$url}/substitutions")->assertOk();

        // A mark operator holds no registrations capability.
        $this->actingAs($this->user('state_mark_operator'), 'platform')->get("{$url}/teams")->assertForbidden();
    }

    public function test_substitutions_are_listed_with_both_names_and_their_decision(): void
    {
        $original = $this->member('Injured');
        $substitution = $this->teams()->requestSubstitution($this->event, $this->registration, [
            'original_participant_id' => $original->id, 'substitute_name' => 'Replacement',
            'reason' => 'Injury', 'user_name' => 'Coordinator',
        ]);
        $this->teams()->rejectSubstitution($substitution, ['note' => 'No evidence', 'user_name' => 'Registrar']);

        $row = $this->actingAs($this->user(), 'platform')
            ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/substitutions")
            ->viewData('page')['props']['substitutions'][0];

        $this->assertSame('Malappuram Sahodaya', $row['sahodaya']);
        $this->assertSame('St Joseph HSS', $row['school']);
        $this->assertSame('Injured', $row['original_name']);
        $this->assertSame('Replacement', $row['substitute_name']);
        $this->assertSame('No evidence', $row['decision_note']);
        $this->assertSame('Registrar', $row['decided_by']);
    }
}
