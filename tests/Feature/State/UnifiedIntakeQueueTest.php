<?php

namespace Tests\Feature\State;

use App\Models\ExternalSahodaya;
use App\Models\FestStateProgram;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * One scrutiny queue for both intake paths. A Sahodaya running on the platform submits through
 * nomination and lands here with its bare tenant uuid as source_tenant_id; one still outside types
 * into the access-code portal and lands here as "external:{uuid}". The queue used to show that raw
 * id and nothing else — no name, no district, no way to tell the two apart or filter either.
 */
class UnifiedIntakeQueueTest extends TestCase
{
    use RefreshDatabase;

    private FestStateProgram $program;

    private Tenant $tenantSahodaya;

    private ExternalSahodaya $outsideSahodaya;

    private ?PlatformUser $admin = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Artisan::call('state:migrate');

        $state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);
        $this->program = FestStateProgram::create([
            'title' => 'Kerala State Kalotsavam 2026', 'state_id' => $state->id,
            'event_type' => 'kalolsavam', 'conduct_levels' => ['sahodaya', 'state'], 'status' => 'published',
        ]);

        $this->tenantSahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'state_id' => $state->id,
            'name' => 'Malappuram Sahodaya', 'is_active' => true,
        ]);

        $this->outsideSahodaya = ExternalSahodaya::create([
            'state_program_id' => $this->program->id, 'name' => 'Kasaragod Sahodaya',
            'district' => 'KASARAGOD', 'access_code' => 'KSGD1234', 'status' => 'active',
        ]);

        $this->intake($this->tenantSahodaya->id, 2, 1);
        $this->intake('external:'.$this->outsideSahodaya->id, 1, 0);
    }

    private function intake(string $source, int $pending, int $approved): StateQualifierIntake
    {
        $intake = StateQualifierIntake::create([
            'state_program_id' => $this->program->id, 'source_tenant_id' => $source,
            'state_id' => $this->program->state_id, 'source_event_id' => 1,
            'idempotency_key' => 'k:'.Str::random(8), 'status' => 'received', 'payload' => [],
        ]);

        foreach (range(1, $pending) as $i) {
            StateQualifierEntry::create(['intake_id' => $intake->id, 'school_id' => 's'.$i, 'student_name' => 'P'.$i, 'status' => 'pending']);
        }
        foreach (range(1, $approved) as $i) {
            if ($approved === 0) {
                break;
            }
            StateQualifierEntry::create(['intake_id' => $intake->id, 'school_id' => 'a'.$i, 'student_name' => 'A'.$i, 'status' => 'approved']);
        }

        return $intake;
    }

    private function actingAdmin(): PlatformUser
    {
        // Created once and reused: users.username is unique, so minting one per request collides
        // the moment a test hits the queue twice.
        return $this->admin ??= tap(PlatformUser::create([
            'name' => 'Super', 'email' => 'super@example.test', 'username' => 'super',
            'password' => 'password', 'email_verified_at' => now(),
        ]), fn (PlatformUser $u) => $u->assignRole('superadmin'));
    }

    private function queue(array $query = [])
    {
        return $this->actingAs($this->actingAdmin(), 'platform')
            ->get('http://superadmin.test/admin/state-workspace/qualifiers?'.http_build_query($query));
    }

    public function test_both_sources_appear_named_and_labelled(): void
    {
        $this->queue()->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('intakes.total', 2)
            ->where('intakes.data.0.source_name', fn ($n) => in_array($n, ['Malappuram Sahodaya', 'Kasaragod Sahodaya'], true))
            ->etc());

        $names = collect($this->queue()->viewData('page')['props']['intakes']['data'])
            ->pluck('source_name')->sort()->values()->all();

        $this->assertSame(['Kasaragod Sahodaya', 'Malappuram Sahodaya'], $names,
            'The queue must name the Sahodaya, not show a raw uuid.');
    }

    public function test_the_source_filter_separates_the_two_paths(): void
    {
        $tenantRows = $this->queue(['source' => 'tenant'])->viewData('page')['props']['intakes']['data'];
        $this->assertCount(1, $tenantRows);
        $this->assertSame('tenant', $tenantRows[0]['source_kind']);
        $this->assertSame('Malappuram Sahodaya', $tenantRows[0]['source_name']);

        $externalRows = $this->queue(['source' => 'external'])->viewData('page')['props']['intakes']['data'];
        $this->assertCount(1, $externalRows);
        $this->assertSame('external', $externalRows[0]['source_kind']);
        $this->assertSame('KASARAGOD', $externalRows[0]['district']);
    }

    public function test_district_filtering_targets_outside_sahodayas(): void
    {
        $rows = $this->queue(['district' => 'kasaragod'])->viewData('page')['props']['intakes']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame('Kasaragod Sahodaya', $rows[0]['source_name']);
    }

    public function test_search_matches_either_kind_of_sahodaya(): void
    {
        $this->assertSame('Malappuram Sahodaya',
            $this->queue(['search' => 'Malappuram'])->viewData('page')['props']['intakes']['data'][0]['source_name']);

        $this->assertSame('Kasaragod Sahodaya',
            $this->queue(['search' => 'Kasaragod'])->viewData('page')['props']['intakes']['data'][0]['source_name']);
    }

    public function test_a_search_matching_nothing_returns_nothing_rather_than_everything(): void
    {
        // The district/search filters are applied as an id whitelist against central tables the
        // state connection cannot join to, so an empty whitelist must not degrade to "no filter".
        $this->assertSame(0, $this->queue(['search' => 'Nonexistent Sahodaya'])->viewData('page')['props']['intakes']['total']);
    }

    public function test_entry_counts_are_broken_down_by_status(): void
    {
        $rows = $this->queue(['source' => 'tenant'])->viewData('page')['props']['intakes']['data'];

        $this->assertSame(3, $rows[0]['entries_count']);
        $this->assertSame(2, $rows[0]['pending_count']);
        $this->assertSame(1, $rows[0]['approved_count']);
    }

    public function test_drafts_stay_out_of_the_queue(): void
    {
        // An outside Sahodaya's portal opens a draft intake as soon as it types its first entry;
        // scrutiny should only see what has actually been submitted.
        StateQualifierIntake::create([
            'state_program_id' => $this->program->id, 'source_tenant_id' => 'external:'.$this->outsideSahodaya->id,
            'state_id' => $this->program->state_id, 'source_event_id' => 0,
            'idempotency_key' => 'k:draft', 'status' => 'draft', 'payload' => [],
        ]);

        $this->assertSame(2, $this->queue()->viewData('page')['props']['intakes']['total']);
    }
}
