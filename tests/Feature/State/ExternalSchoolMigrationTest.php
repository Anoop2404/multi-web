<?php

namespace Tests\Feature\State;

use App\Models\ExternalSahodaya;
use App\Models\ExternalSchool;
use App\Models\FestStateProgram;
use App\Models\PlatformState;
use App\Models\State\StateQualifierEntry;
use App\Models\Tenant;
use App\Models\TenantProvisioningChecklist;
use App\Services\State\ExternalIntakeService;
use App\Services\State\ExternalSchoolMigrator;
use App\Services\State\SahodayaPromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 3 of docs/STATE_ADMIN_TENANT_PROMOTION_AND_UAT_PLAN_2026_09_23.md — the schools on a
 * promoted Sahodaya's access-code roster become real school tenants underneath it.
 *
 * The suite runs on one shared database (TENANCY_DATABASE_PER_SAHODAYA=false, see phpunit.xml), so
 * the "schools share the parent's database" property and the username-only login are exercised
 * manually instead (plan §4.6); everything here is the tenant/prefix/link/roster behaviour, which is
 * identical either way.
 */
class ExternalSchoolMigrationTest extends TestCase
{
    use RefreshDatabase;

    private FestStateProgram $program;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        Artisan::call('db:seed', ['--class' => 'SubscriptionPlanSeeder']);
        Artisan::call('state:migrate');

        $state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);
        $this->program = FestStateProgram::create([
            'title'          => 'Kerala State Kalotsavam 2026',
            'state_id'       => $state->id,
            'event_type'     => 'kalolsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'published',
        ]);
    }

    private function sahodaya(string $name = 'Idukki Sahodaya'): ExternalSahodaya
    {
        return app(ExternalIntakeService::class)->createSahodaya($this->program, [
            'name'          => $name,
            'district'      => 'IDUKKI',
            'contact_email' => strtolower(str_replace(' ', '', $name)).'@example.test',
            'source'        => 'seeded',
        ]);
    }

    private function school(ExternalSahodaya $sahodaya, string $name): ExternalSchool
    {
        return app(ExternalIntakeService::class)->addSchool($sahodaya, ['name' => $name]);
    }

    private function promoted(string $name = 'Idukki Sahodaya'): ExternalSahodaya
    {
        $ext = $this->sahodaya($name);
        app(SahodayaPromotionService::class)->promote($ext);

        return $ext->fresh();
    }

    public function test_a_school_becomes_a_child_tenant_of_its_promoted_sahodaya(): void
    {
        $ext = $this->promoted();
        $school = $this->school($ext, 'St Joseph HSS');

        $tenant = app(ExternalSchoolMigrator::class)->migrate($school->fresh());

        $this->assertSame('school', $tenant->type);
        $this->assertSame($ext->tenant_id, $tenant->parent_id, 'The school must hang off its own Sahodaya.');
        $this->assertSame($ext->tenant->state_id, $tenant->state_id);
        $this->assertTrue($tenant->is_active);
        $this->assertSame('SJH', $tenant->school_prefix);

        // The link back is what makes a re-run idempotent and what closes the old school portal.
        $this->assertSame($tenant->id, $school->fresh()->tenant_id);
        $this->assertNotNull($school->fresh()->promoted_at);

        $this->assertTrue(
            TenantProvisioningChecklist::where('tenant_id', $tenant->id)->where('step_key', 'tenant_created')->exists()
        );
    }

    public function test_schools_get_distinct_prefixes_within_the_same_sahodaya(): void
    {
        $ext = $this->promoted();
        $a = $this->school($ext, 'St Joseph HSS');
        $b = $this->school($ext, 'St Joseph HSS Annexe');

        $migrator = app(ExternalSchoolMigrator::class);
        $first = $migrator->migrate($a->fresh());
        $second = $migrator->migrate($b->fresh());

        $this->assertNotSame($first->school_prefix, $second->school_prefix);
        // Enforced by 2026_06_20_000003_unique_school_prefix_per_sahodaya.php — a collision here
        // would be a constraint violation, not a cosmetic clash.
        $this->assertSame(
            2,
            Tenant::where('parent_id', $ext->tenant_id)->distinct()->count('school_prefix')
        );
    }

    public function test_a_school_whose_sahodaya_is_not_promoted_is_blocked(): void
    {
        $ext = $this->sahodaya();
        $school = $this->school($ext, 'St Joseph HSS');

        $reason = app(ExternalSchoolMigrator::class)->blockingReason($school);
        $this->assertNotNull($reason);
        $this->assertStringContainsString('has not been promoted', $reason);

        $this->expectException(RuntimeException::class);
        app(ExternalSchoolMigrator::class)->migrate($school);
    }

    public function test_appeal_pool_schools_are_never_migrated(): void
    {
        $ext = $this->promoted();
        $appeal = app(ExternalIntakeService::class)->ensureAppealSchool($ext);

        $this->assertStringContainsString(
            'Appeal pool',
            (string) app(ExternalSchoolMigrator::class)->blockingReason($appeal)
        );

        $this->expectException(RuntimeException::class);
        app(ExternalSchoolMigrator::class)->migrate($appeal);
    }

    public function test_migrating_twice_does_not_create_a_second_tenant(): void
    {
        $ext = $this->promoted();
        $school = $this->school($ext, 'St Joseph HSS');

        $first = app(ExternalSchoolMigrator::class)->migrate($school->fresh());

        $this->expectException(RuntimeException::class);

        try {
            app(ExternalSchoolMigrator::class)->migrate($school->fresh());
        } finally {
            $this->assertSame(1, Tenant::where('parent_id', $ext->tenant_id)->where('type', 'school')->count());
            $this->assertSame($first->id, $school->fresh()->tenant_id);
        }
    }

    public function test_qualifier_entries_are_left_in_the_state_ledger_and_stay_reachable(): void
    {
        // Deliberate design, not an omission: `students` needs a school_class_id pointing at a real
        // school_classes row plus a tenant-unique admission_number, and a freshly promoted Sahodaya
        // has no classes — projecting entries would mean inventing a class structure and fake
        // admission numbers the school must then clean up. The entries stay put, and
        // external_schools.tenant_id ties them to the new school tenant.
        $ext = $this->promoted();
        $school = $this->school($ext, 'St Joseph HSS');

        StateQualifierEntry::create([
            'intake_id'      => app(ExternalIntakeService::class)->addEntry($school, [
                'item_code'    => 'LM01',
                'item_name'    => 'Light Music',
                'student_name' => 'Athira Menon',
                'class_name'   => 'Class 10',
            ])->intake_id,
            'school_id'      => $school->id,
            'student_name'   => 'Rahul Nair',
            'item_code'      => 'LM01',
        ]);

        $before = StateQualifierEntry::where('school_id', $school->id)->count();
        $this->assertSame(2, $before);

        $tenant = app(ExternalSchoolMigrator::class)->migrate($school->fresh());

        $this->assertSame($before, StateQualifierEntry::where('school_id', $school->id)->count(),
            'Entries must not be moved, copied or deleted.');
        $this->assertSame($tenant->id, $school->fresh()->tenant_id,
            'The link from the entries to the new school tenant is external_schools.tenant_id.');
    }

    public function test_the_plan_preview_writes_nothing(): void
    {
        $ext = $this->promoted();
        $school = $this->school($ext, 'St Joseph HSS');

        $plan = app(ExternalSchoolMigrator::class)->plan($school);

        $this->assertTrue($plan['migratable']);
        $this->assertSame('SJH', $plan['prefix']);
        $this->assertSame($school->username, $plan['username']);
        $this->assertNull($school->fresh()->tenant_id);
        $this->assertSame(0, Tenant::where('type', 'school')->count());
    }

    public function test_the_plan_respects_prefixes_claimed_earlier_in_the_same_batch(): void
    {
        $ext = $this->promoted();
        $school = $this->school($ext, 'St Joseph HSS');

        $plan = app(ExternalSchoolMigrator::class)->plan($school, ['SJH' => true]);

        $this->assertNotSame('SJH', $plan['prefix']);
    }
}
