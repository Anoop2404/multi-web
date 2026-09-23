<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateSahodaya;
use App\Support\StateFestReportCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 8 of the State Kalotsav module — the report catalog and its parity contract.
 *
 * Scope is deliberate: the reports reachable from the State sidebar and workspace tabs, mirroring
 * what the Sahodaya module exposes in its own navigation, not the full inventory of export
 * permutations. The parity test below is what stops a State report going silently missing — a
 * Sahodaya report either has a State counterpart, or is recorded as not applicable with a reason.
 */
class StateReportCatalogTest extends TestCase
{
    use RefreshDatabase;

    private StateFestEvent $event;

    private PlatformState $state;

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
            'name' => 'State Finals', 'slug' => 'state-finals', 'status' => 'active',
        ]);
    }

    private function admin(string $role = 'state_admin'): PlatformUser
    {
        $user = PlatformUser::create([
            'name' => $role, 'email' => $role.'@example.test', 'username' => $role,
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * The parity contract. Every report the Sahodaya module puts in its own navigation must have a
     * State counterpart, or be listed as not applicable with a stated reason — never simply absent.
     */
    public function test_every_menu_reachable_sahodaya_report_has_a_state_counterpart_or_a_reason(): void
    {
        // Taken from the Outputs section of resources/js/support/sahodayaEventNav.js — the reports a
        // Sahodaya admin can actually reach from a menu.
        $sahodayaMenuReports = [
            'overall-ranking'      => 'overall-sahodaya-ranking',
            'category-item-matrix' => 'category-sahodaya-points',
            'category-wise-points' => 'category-sahodaya-points',
            'student-wise'         => 'student-wise',
            'item-wise'            => 'item-wise-results',
            'item-counts'          => 'item-counts',
            'item-schedule'        => 'item-schedule',
            'schedule-clashes'     => 'schedule-clashes',
            'school-participation' => 'sahodaya-participation',
            'unique-participants'  => 'unique-participants',
            'student-limits'       => 'slot-usage',
        ];

        $missing = [];

        foreach ($sahodayaMenuReports as $sahodayaId => $stateId) {
            if (StateFestReportCatalog::find($stateId) === null
                && ! array_key_exists($sahodayaId, StateFestReportCatalog::notApplicable())) {
                $missing[] = "{$sahodayaId} (expected State report \"{$stateId}\")";
            }
        }

        $this->assertSame([], $missing, "State reports missing with no recorded reason:\n".implode("\n", $missing));
    }

    public function test_reports_that_are_not_applicable_say_why(): void
    {
        foreach (StateFestReportCatalog::notApplicable() as $id => $reason) {
            $this->assertNotEmpty($reason, "{$id} is marked not applicable without a reason.");
            $this->assertNull(StateFestReportCatalog::find($id), "{$id} is both implemented and marked not applicable.");
        }
    }

    public function test_every_report_declares_a_known_group_and_at_least_one_format(): void
    {
        $groups = array_keys(StateFestReportCatalog::groups());

        foreach (StateFestReportCatalog::reports() as $report) {
            $this->assertContains($report['group'], $groups, "{$report['id']} is in an unknown group.");
            $this->assertNotEmpty($report['formats'], "{$report['id']} offers no download format.");
            $this->assertEmpty(
                array_diff($report['formats'], ['pdf', 'xls', 'csv']),
                "{$report['id']} offers an unsupported format.",
            );
        }
    }

    public function test_an_unavailable_report_states_which_phase_it_waits_on(): void
    {
        foreach (StateFestReportCatalog::reports() as $report) {
            if (! ($report['available'] ?? false)) {
                $this->assertNotEmpty(
                    $report['blocked_by'] ?? null,
                    "{$report['id']} is unavailable without saying why.",
                );
            }
        }
    }

    public function test_opening_an_unavailable_report_explains_itself_rather_than_showing_an_empty_table(): void
    {
        // Whichever report is still waiting on a later phase — named dynamically so this test does
        // not have to be edited each time one is unblocked.
        $blocked = collect(StateFestReportCatalog::reports())->firstWhere('available', false);

        if (! $blocked) {
            $this->markTestSkipped('Every catalogued report is available.');
        }

        $response = $this->actingAs($this->admin(), 'platform')
            ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/reports/{$blocked['id']}");

        // 409, not 404: the report exists, its data does not yet.
        $response->assertStatus(409);
    }

    public function test_every_available_report_renders(): void
    {
        $admin = $this->admin();
        $this->seedOneRegistration();

        foreach (StateFestReportCatalog::available() as $report) {
            $this->actingAs($admin, 'platform')
                ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/reports/{$report['id']}")
                ->assertOk();
        }
    }

    public function test_every_available_report_downloads_in_each_format_it_offers(): void
    {
        $admin = $this->admin();
        $this->seedOneRegistration();

        foreach (StateFestReportCatalog::available() as $report) {
            foreach ($report['formats'] as $format) {
                // PDF rendering is slow and exercised separately; the data path is identical.
                if ($format === 'pdf') {
                    continue;
                }

                $this->actingAs($admin, 'platform')
                    ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/reports/{$report['id']}/download?format={$format}")
                    ->assertOk();
            }
        }
    }

    public function test_a_format_the_report_does_not_offer_is_refused(): void
    {
        $this->actingAs($this->admin(), 'platform')
            ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/reports/unique-participants/download?format=pdf")
            ->assertStatus(422);
    }

    public function test_reports_are_reachable_by_a_read_only_report_user(): void
    {
        // A report user holds no write capability at all, and must still reach every report.
        $this->actingAs($this->admin('state_report_user'), 'platform')
            ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/reports")
            ->assertOk();
    }

    public function test_a_mark_operator_cannot_reach_reports(): void
    {
        $this->actingAs($this->admin('state_mark_operator'), 'platform')
            ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/reports")
            ->assertForbidden();
    }

    public function test_reports_carry_both_the_sahodaya_and_the_school(): void
    {
        $this->seedOneRegistration();

        $page = $this->actingAs($this->admin(), 'platform')
            ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/reports/registration-master")
            ->viewData('page');

        $this->assertSame(['Sahodaya', 'School', 'Item', 'Participant(s)', 'Status'], $page['props']['headers']);
        $this->assertContains('Malappuram Sahodaya', $page['props']['rows'][0]);
        $this->assertContains('St Joseph HSS', $page['props']['rows'][0], 'The School must never be dropped.');
    }

    private function seedOneRegistration(): void
    {
        $sahodaya = StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->state->id, 'name' => 'Malappuram Sahodaya',
            'tenant_id' => (string) Str::uuid(), 'origin' => StateSahodaya::ORIGIN_MANAGED,
        ]);

        StateFestRegistration::create([
            'state_event_id' => $this->event->id, 'sahodaya_id' => $sahodaya->id,
            'sahodaya_name' => 'Malappuram Sahodaya', 'school_id' => 'sjh', 'school_name' => 'St Joseph HSS',
            'item_code' => 'LM01', 'status' => 'approved',
        ]);
    }
}
