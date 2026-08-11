<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
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

    public function test_state_admin_can_review_conduct_and_publish_a_complete_state_flow(): void
    {
        $admin = User::factory()->create(['tenant_id' => null, 'must_change_password' => false]);
        $admin->assignRole('state_admin');

        $program = FestStateProgram::create([
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

    public function test_dedicated_state_domain_exposes_domain_local_action_urls(): void
    {
        $admin = User::factory()->create(['tenant_id' => null, 'must_change_password' => false]);
        $admin->assignRole('state_admin');
        $event = StateFestEvent::create([
            'state_program_id' => '019fea66-9b8d-7361-9828-1f6bbacaf36e',
            'name' => 'Dedicated State Domain',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->withHeader('Host', 'state.localhost')
            ->get("http://state.localhost/fest/{$event->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StateAdmin/Fest/Show')
                ->where('actionUrls.attendance', "/fest/{$event->id}/attendance")
                ->where('actionUrls.marks', "/fest/{$event->id}/marks")
                ->where('actionUrls.publishResults', "/fest/{$event->id}/publish-results"));
    }
}
