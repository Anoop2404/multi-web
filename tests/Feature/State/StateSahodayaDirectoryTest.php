<?php

namespace Tests\Feature\State;

use App\Models\ExternalSahodaya;
use App\Models\FestStateProgram;
use App\Models\PlatformState;
use App\Models\State\StateQualifierIntake;
use App\Models\State\StateSahodaya;
use App\Models\Tenant;
use App\Services\State\ExternalIntakeService;
use App\Services\State\SahodayaPromotionService;
use App\Services\State\StateQualifierIntakeService;
use App\Services\State\StateSahodayaDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 1 of the State Kalotsav module — one canonical Sahodaya identity.
 *
 * The State module keys everything on the Sahodaya rather than the School, so the identity has to
 * survive the one event that used to change it: an outside Sahodaya being promoted to a platform
 * tenant. Before the directory, its history sat under "external:{uuid}" and its later submissions
 * under the tenant uuid — the same body counted twice in standings, slot usage, fees and reports.
 */
class StateSahodayaDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private PlatformState $state;

    private FestStateProgram $program;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        Artisan::call('db:seed', ['--class' => 'SubscriptionPlanSeeder']);
        Artisan::call('state:migrate');

        $this->state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);
        $this->program = FestStateProgram::create([
            'title' => 'Kerala State Kalotsavam 2026', 'state_id' => $this->state->id,
            'event_type' => 'kalolsavam', 'conduct_levels' => ['sahodaya', 'state'], 'status' => 'published',
        ]);
    }

    private function directory(): StateSahodayaDirectory
    {
        return app(StateSahodayaDirectory::class);
    }

    private function tenant(string $name = 'Malappuram Sahodaya'): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'state_id' => $this->state->id,
            'name' => $name, 'is_active' => true,
        ]);
    }

    private function outside(string $name = 'Kasaragod Sahodaya'): ExternalSahodaya
    {
        return app(ExternalIntakeService::class)->createSahodaya($this->program, [
            'name' => $name, 'district' => 'KASARAGOD',
            'contact_email' => Str::slug($name).'@example.test', 'source' => 'seeded',
        ]);
    }

    private function submit(string $sourceKey, string $student): StateQualifierIntake
    {
        return app(StateQualifierIntakeService::class)->receive('k:'.Str::random(10), [
            'state_program_id' => $this->program->id,
            'source_event_id'  => 1,
            'entries' => [[
                'school_id' => 'sch-1', 'school_name' => 'St Joseph HSS',
                'item_id' => (string) Str::uuid(), 'item_code' => 'LM01',
                'student_name' => $student, 'position' => 1, 'grade' => 'A',
            ]],
        ], $sourceKey);
    }

    public function test_a_managed_sahodaya_gets_a_directory_identity(): void
    {
        $tenant = $this->tenant();

        $row = $this->directory()->forTenant($tenant);

        $this->assertSame('Malappuram Sahodaya', $row->name);
        $this->assertSame(StateSahodaya::ORIGIN_MANAGED, $row->origin);
        $this->assertTrue($row->isOnPlatform());
        $this->assertSame([$tenant->id], $row->sourceKeys());
    }

    public function test_an_outside_sahodaya_gets_one_too(): void
    {
        $outside = $this->outside();

        $row = $this->directory()->forExternal($outside);

        $this->assertSame('Kasaragod Sahodaya', $row->name);
        $this->assertSame(StateSahodaya::ORIGIN_EXTERNAL, $row->origin);
        $this->assertSame('KASARAGOD', $row->district);
        $this->assertFalse($row->isOnPlatform());
        $this->assertSame(["external:{$outside->id}"], $row->sourceKeys());
    }

    public function test_resolving_the_same_source_twice_does_not_create_a_second_identity(): void
    {
        $tenant = $this->tenant();
        $outside = $this->outside();

        $this->directory()->resolve($tenant->id, $this->state->id);
        $this->directory()->resolve($tenant->id, $this->state->id);
        $this->directory()->resolve("external:{$outside->id}", $this->state->id);
        $this->directory()->resolve("external:{$outside->id}", $this->state->id);

        $this->assertSame(2, StateSahodaya::count());
    }

    public function test_promotion_keeps_one_identity_and_both_source_keys_resolve_to_it(): void
    {
        // The case the directory exists for.
        $outside = $this->outside();
        $this->submit("external:{$outside->id}", 'Before Promotion');

        $identityBefore = StateSahodaya::sole();
        $this->assertFalse($identityBefore->isOnPlatform());

        $tenant = app(SahodayaPromotionService::class)->promote($outside->fresh());

        $this->assertSame(1, StateSahodaya::count(), 'Promotion must not create a second identity.');

        $identityAfter = StateSahodaya::sole();
        $this->assertSame($identityBefore->id, $identityAfter->id);
        $this->assertTrue($identityAfter->isOnPlatform());
        $this->assertTrue($identityAfter->arrivedExternal(), 'Origin must still record that it came from outside.');
        $this->assertEqualsCanonicalizing(
            [$tenant->id, "external:{$outside->id}"],
            $identityAfter->sourceKeys(),
        );
    }

    public function test_submissions_from_before_and_after_promotion_land_on_the_same_identity(): void
    {
        // The actual double-count: the same body submitting under two different raw keys.
        $outside = $this->outside();
        $before = $this->submit("external:{$outside->id}", 'Before Promotion');

        $tenant = app(SahodayaPromotionService::class)->promote($outside->fresh());

        $after = $this->submit($tenant->id, 'After Promotion');

        $this->assertNotSame($before->source_tenant_id, $after->source_tenant_id, 'The raw keys do differ.');
        $this->assertSame(
            $before->fresh()->sahodaya_id,
            $after->fresh()->sahodaya_id,
            'But both must resolve to one Sahodaya, or standings and fees count it twice.',
        );
        $this->assertSame(1, StateSahodaya::count());
    }

    public function test_a_duplicate_identity_created_before_promotion_is_absorbed(): void
    {
        // A Sahodaya that had somehow submitted both ways before the directory existed.
        $outside = $this->outside();
        $this->submit("external:{$outside->id}", 'Outside Submission');

        $tenant = $this->tenant('Kasaragod Sahodaya');
        $stray = $this->directory()->forTenant($tenant);
        $strayIntake = $this->submit($tenant->id, 'Managed Submission');

        $this->assertSame(2, StateSahodaya::count());

        $outside->forceFill(['tenant_id' => $tenant->id])->save();
        $this->directory()->linkPromotedTenant($outside->fresh(), $tenant);

        $this->assertSame(1, StateSahodaya::count(), 'The stray identity must be absorbed, not left behind.');

        $surviving = StateSahodaya::sole();
        $this->assertSame(StateSahodaya::ORIGIN_EXTERNAL, $surviving->origin, 'The row carrying the earlier history survives.');
        $this->assertSame($surviving->id, $strayIntake->fresh()->sahodaya_id, 'Its intakes must follow it.');
        $this->assertNull(StateSahodaya::find($stray->id));
    }

    public function test_the_name_is_snapshotted_and_does_not_follow_a_later_rename(): void
    {
        // State reporting must not depend on live tenant data — an event's history keeps the name
        // the Sahodaya had when it took part.
        $tenant = $this->tenant('Original Name Sahodaya');
        $row = $this->directory()->forTenant($tenant);

        $tenant->forceFill(['name' => 'Renamed Later Sahodaya'])->save();

        $this->assertSame('Original Name Sahodaya', $row->fresh()->name);
    }

    public function test_the_school_stays_attached_to_every_entry(): void
    {
        // Sahodaya is the competing organization; the School it came from must never be lost.
        $outside = $this->outside();
        $intake = $this->submit("external:{$outside->id}", 'Athira Menon');

        $entry = $intake->entries()->sole();
        $this->assertSame('St Joseph HSS', $entry->school_name);
        $this->assertSame('Kasaragod Sahodaya', $intake->fresh()->sahodaya_name);
    }

    public function test_an_unresolvable_source_key_does_not_break_intake(): void
    {
        // Manual state-admin entry can carry an id matching nothing; recording it without an
        // identity is better than refusing the submission at the door.
        $intake = $this->submit('not-a-real-sahodaya', 'Manual Entry');

        $this->assertNull($intake->sahodaya_id);
        $this->assertSame('not-a-real-sahodaya', $intake->source_tenant_id);
    }

    public function test_the_directory_is_scoped_per_state_and_fails_closed(): void
    {
        $this->directory()->forTenant($this->tenant());

        $this->assertCount(1, $this->directory()->forStateListing($this->state->id));
        $this->assertCount(0, $this->directory()->forStateListing(null));
    }
}
