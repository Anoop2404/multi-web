<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\PlatformState;
use App\Models\State\StateAttendance;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateQualifierEntry;
use App\Models\StateRemittance;
use App\Models\Tenant;
use App\Models\User;
use App\Services\State\StateQualifierIntakeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StateAdminFlowHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** A state_admin with no state_id is now blocked from everything (fail-closed) — every test needs a real, assigned state. */
    private function makeStateAdmin(): array
    {
        $state = PlatformState::create(['code' => 'TS', 'name' => 'Test State']);
        $admin = User::factory()->create(['tenant_id' => null, 'must_change_password' => false, 'state_id' => $state->id]);
        $admin->assignRole('state_admin');

        return [$admin, $state];
    }

    public function test_state_admin_can_review_conduct_and_publish_a_complete_state_flow(): void
    {
        [$admin, $state] = $this->makeStateAdmin();

        $program = FestStateProgram::create([
            'state_id' => $state->id,
            'title' => 'State Route Flow 2026',
            'event_type' => 'kalotsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status' => 'published',
            'level_fees' => ['state' => ['fee_model' => 'per_student', 'individual_amount' => 500]],
        ]);
        $sahodaya = Tenant::create([
            'id' => 'state-http-sahodaya',
            'name' => 'State HTTP Sahodaya',
            'type' => 'sahodaya',
        ]);

        $itemId = '019fea66-9b8d-7361-9828-1f6bbacaf36e';
        $intake = app(StateQualifierIntakeService::class)->receive('state-http-flow', [
            'state_program_id' => $program->id,
            'source_event_id' => 7001,
            'entries' => [
                [
                    'school_id' => 'state-school-1',
                    'school_name' => 'State Model School',
                    'item_id' => $itemId,
                    'item_code' => 'LM01',
                    'student_name' => 'Accepted Finalist',
                    'position' => 1,
                    'grade' => 'A',
                ],
                [
                    'school_id' => 'state-school-2',
                    'school_name' => 'Rejected School',
                    'item_id' => $itemId,
                    'item_code' => 'LM01',
                    'student_name' => 'Rejected Finalist',
                    'position' => 2,
                    'grade' => 'A',
                ],
            ],
        ], $sahodaya->id);
        $rejectedEntry = $intake->entries()->where('student_name', 'Rejected Finalist')->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/state-workspace/qualifiers/{$intake->id}/entries/{$rejectedEntry->id}/review", ['status' => 'rejected'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post("/admin/state-workspace/qualifiers/{$intake->id}/approve")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('rejected', StateQualifierEntry::findOrFail($rejectedEntry->id)->status);
        $event = StateFestEvent::where('state_program_id', $program->id)->firstOrFail();
        $registration = $event->registrations()->with('participants')->sole();
        $participant = $registration->participants->sole();

        $remittance = StateRemittance::where('sahodaya_id', $sahodaya->id)->sole();
        $this->assertSame(500.0, (float) $remittance->amount);
        $this->assertSame($program->id, $remittance->source_breakdown['state_program_id']);

        $this->actingAs($admin)
            ->post("/admin/state-workspace/fest/{$event->id}/assign-chest-numbers")
            ->assertRedirect();
        $this->assertSame('101', $participant->fresh()->chest_number);

        $this->actingAs($admin)
            ->post("/admin/state-workspace/fest/{$event->id}/attendance", [
                'item_id' => $itemId,
                'participant_id' => $participant->id,
                'status' => 'present',
            ])
            ->assertRedirect();
        $this->assertSame('present', StateAttendance::where('participant_id', $participant->id)->sole()->status);

        $this->actingAs($admin)
            ->post("/admin/state-workspace/fest/{$event->id}/marks", [
                'participant_id' => $participant->id,
                'score' => 91,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post("/admin/state-workspace/fest/{$event->id}/publish-results")
            ->assertRedirect()
            ->assertSessionHas('success');

        $mark = StateFestMark::where('participant_id', $participant->id)->sole();
        $this->assertSame('published', $mark->status);
        $this->assertSame(1, $mark->position);
        $this->assertTrue($event->fresh()->results_published);

        $this->get('/state/results')
            ->assertOk()
            ->assertSee('Accepted Finalist')
            ->assertDontSee('Rejected Finalist');
    }

    public function test_state_admin_can_view_state_program_show_page_with_propagations(): void
    {
        [$admin, $state] = $this->makeStateAdmin();

        $program = FestStateProgram::create([
            'state_id' => $state->id,
            'title' => 'State Program Show Test',
            'event_type' => 'kalotsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status' => 'published',
        ]);
        $sahodaya = Tenant::create([
            'id' => 'test-show-sahodaya',
            'name' => 'Show Page Sahodaya',
            'type' => 'sahodaya',
            'is_active' => true,
        ]);

        \App\Models\FestStateProgramPropagation::create([
            'state_program_id' => $program->id,
            'sahodaya_id' => $sahodaya->id,
            'tenant_event_id' => 34,
            'level_round' => 'sahodaya',
        ]);

        $this->actingAs($admin)
            ->get("/admin/state-programs/{$program->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StatePrograms/Show', false)
                ->has('allSahodayas', 1)
                ->where('allSahodayas.0.id', $sahodaya->id)
                ->where('allSahodayas.0.deployed', true)
                ->where('allSahodayas.0.tenant_event_id', 34)
                ->where('allSahodayas.0.sahodaya_customized_at', null));
    }

    public function test_dedicated_state_domain_exposes_domain_local_action_urls(): void
    {
        [$admin, $state] = $this->makeStateAdmin();
        $event = StateFestEvent::create([
            'state_program_id' => '019fea66-9b8d-7361-9828-1f6bbacaf36e',
            'state_id' => $state->id,
            'name' => 'Dedicated State Domain',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->withHeader('Host', 'state.localhost')
            ->get("http://state.localhost/fest/{$event->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StateAdmin/Fest/Show', false)
                ->where('actionUrls.attendance', "/fest/{$event->id}/attendance")
                ->where('actionUrls.marks', "/fest/{$event->id}/marks")
                ->where('actionUrls.publishResults', "/fest/{$event->id}/publish-results"));
    }

    public function test_state_admin_can_update_state_program_item(): void
    {
        [$admin, $state] = $this->makeStateAdmin();

        $program = FestStateProgram::create([
            'state_id' => $state->id,
            'title' => 'State Item Update Program',
            'event_type' => 'kalotsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status' => 'draft',
        ]);

        $item = $program->items()->create([
            'title' => 'Group Song',
            'item_code' => '501',
            'category' => 'music',
            'class_group' => 'category_5',
            'participant_type' => 'group',
            'qualify_count' => 1,
        ]);

        $response = $this->actingAs($admin)->put("/admin/state-programs/{$program->id}/items/{$item->id}", [
            'title' => 'Group Song (Renamed)',
            'item_code' => '501-A',
            'category' => 'traditional',
            'class_group' => 'category_5',
            'participant_type' => 'group',
            'qualify_count' => 2,
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $item->refresh();
        $this->assertSame('Group Song (Renamed)', $item->title);
        $this->assertSame('501-A', $item->item_code);
        $this->assertSame('traditional', $item->category);
        $this->assertSame('category_5', $item->class_group);
        $this->assertSame(2, $item->qualify_count);
    }

    public function test_state_admin_can_add_qualifier_intake_and_edit_qualifier_entries(): void
    {
        [$admin, $state] = $this->makeStateAdmin();

        $program = FestStateProgram::create([
            'state_id' => $state->id,
            'title' => 'State Qualifier Test Program',
            'event_type' => 'kalotsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status' => 'published',
        ]);
        $sahodaya = Tenant::create([
            'id' => 'qualifier-test-sahodaya',
            'name' => 'Qualifier Test Sahodaya',
            'type' => 'sahodaya',
        ]);

        $response = $this->actingAs($admin)->post('/admin/state-workspace/qualifiers/intake', [
            'state_program_id' => $program->id,
            'source_tenant_id' => $sahodaya->id,
            'entries' => [
                [
                    'student_name' => 'Initial Student',
                    'school_name' => 'Silver Hills School',
                    'item_code' => '101',
                    'item_name' => 'Recitation Malayalam',
                    'position' => 1,
                    'grade' => 'A',
                ],
            ],
        ]);
        $response->assertRedirect()->assertSessionHas('success');

        $intake = \App\Models\State\StateQualifierIntake::where('source_tenant_id', $sahodaya->id)->sole();
        $entry = $intake->entries()->firstOrFail();

        $updateResponse = $this->actingAs($admin)->put("/admin/state-workspace/qualifiers/{$intake->id}/entries/{$entry->id}", [
            'student_name' => 'Updated Student Name',
            'school_name' => 'Silver Hills Higher Secondary',
            'item_code' => '101',
            'item_name' => 'Recitation Malayalam',
            'position' => 1,
            'grade' => 'A+',
            'status' => 'approved',
        ]);
        $updateResponse->assertRedirect()->assertSessionHas('success');

        $entry->refresh();
        $this->assertSame('Updated Student Name', $entry->student_name);
        $this->assertSame('Silver Hills Higher Secondary', $entry->school_name);
        $this->assertSame('A+', $entry->grade);
        $this->assertSame('approved', $entry->status);
    }
}
