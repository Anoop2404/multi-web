<?php

namespace Tests\Feature\Console;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Stancl's bare `tenants:migrate` (no --tenants filter) iterates every row in the
 * tenants table — Sahodayas and schools alike — but only Sahodaya-type tenants have
 * their own database in this app (config('tenancy.database_per_sahodaya'): "member
 * schools share the parent DB"). Confirms tenants:migrate-sahodayas builds its
 * --tenants list from Sahodaya ids only, excluding every school.
 */
class MigrateSahodayaTenantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_delegates_to_tenants_migrate_with_only_sahodaya_ids(): void
    {
        $sahodayaA = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Sahodaya A',
            'is_active' => true,
        ]);
        $sahodayaB = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Sahodaya B',
            'is_active' => true,
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'A School',
            'parent_id' => $sahodayaA->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        Artisan::shouldReceive('call')
            ->once()
            ->withArgs(function (string $command, array $params) use ($sahodayaA, $sahodayaB, $school) {
                if ($command !== 'tenants:migrate') {
                    return false;
                }

                $tenants = $params['--tenants'] ?? [];
                sort($tenants);
                $expected = [$sahodayaA->id, $sahodayaB->id];
                sort($expected);

                return $tenants === $expected
                    && ! in_array($school->id, $tenants, true)
                    && $params['--force'] === true;
            })
            ->andReturn(0);

        $this->assertSame(0, $this->runCommandDirectly(['--force' => true]));
    }

    public function test_warns_and_skips_when_no_sahodaya_tenants_exist(): void
    {
        Artisan::shouldReceive('call')->never();

        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $exitCode = $this->runCommandDirectly(['--force' => true], $output);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No Sahodaya tenants found', $output->fetch());
    }

    /**
     * Runs the command directly (bypassing the Artisan facade/console Kernel this test
     * mocks Artisan::call() through) so the mock only ever intercepts the ONE delegated
     * call the command under test makes internally, not the test's own invocation of it.
     */
    private function runCommandDirectly(array $input, ?\Symfony\Component\Console\Output\OutputInterface $output = null): int
    {
        $command = $this->app->make(\App\Console\Commands\MigrateSahodayaTenants::class);
        $command->setLaravel($this->app);

        return $command->run(
            new \Symfony\Component\Console\Input\ArrayInput($input, $command->getDefinition()),
            $output ?? new \Symfony\Component\Console\Output\NullOutput(),
        );
    }
}
