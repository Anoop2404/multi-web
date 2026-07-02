<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class E2eProvisionUsers extends Command
{
    protected $signature = 'e2e:provision-users {--password=password}';

    protected $description = 'Create demo users for every role (Playwright E2E / UX audits)';

    public function handle(): int
    {
        $this->callSilent('db:seed', ['--class' => RolesAndPermissionsSeeder::class]);

        $password = (string) $this->option('password');

        $sahodaya = Tenant::where('subdomain', 'malappuram')->where('type', 'sahodaya')->first();
        $school = Tenant::where('subdomain', 'amu-school')->where('type', 'school')->first();

        if (! $sahodaya || ! $school) {
            $this->error('Run DemoTenantsSeeder first: php artisan db:seed --class=DemoTenantsSeeder');

            return self::FAILURE;
        }

        $this->upsertUser('state_admin@e2e.test', 'E2E State Admin', null, ['state_admin'], $password);
        $this->upsertUser('sahodaya_staff@e2e.test', 'E2E Sahodaya Staff', $sahodaya->id, ['sahodaya_staff'], $password);
        $this->upsertUser('judge@e2e.test', 'E2E Judge', $sahodaya->id, ['judge'], $password);
        $this->upsertUser('exam@e2e.test', 'E2E Exam Controller', $sahodaya->id, ['exam_controller'], $password);
        $this->upsertUser('group@e2e.test', 'E2E Group Admin', $school->id, ['group_admin'], $password);
        $this->upsertUser('festops@e2e.test', 'E2E Fest Ops', $sahodaya->id, ['fest_ops'], $password);
        $this->upsertUser('mark_coordinator@e2e.test', 'E2E Mark Coordinator', $sahodaya->id, ['mark_entry_coordinator'], $password);

        $houseId = null;
        $sahodaya->run(function () use ($school, &$houseId) {
            $house = \App\Models\SchoolHouse::firstOrCreate(
                ['tenant_id' => $school->id, 'name' => 'E2E Blue House'],
                ['color' => '#2563eb', 'sort_order' => 1]
            );
            $houseId = $house->id;
        });

        $houseUser = $this->upsertUser('house@e2e.test', 'E2E House Admin', $school->id, ['house_admin'], $password);
        $houseUser->update(['school_house_id' => $houseId]);
        $teacherUser = $this->upsertUser('teacher@e2e.test', 'E2E Teacher', $school->id, ['teacher'], $password);
        $studentUser = $this->upsertUser('student@e2e.test', 'E2E Student', $school->id, ['student'], $password);

        $sahodaya->run(function () use ($school, $teacherUser, $studentUser) {
            Teacher::updateOrCreate(
                ['tenant_id' => $school->id, 'email' => 'teacher@e2e.test'],
                ['name' => 'E2E Teacher', 'status' => 'active', 'user_id' => $teacherUser->id]
            );

            $student = Student::where('tenant_id', $school->id)->active()->first();
            if (! $student) {
                $student = Student::create([
                    'tenant_id' => $school->id,
                    'name' => 'E2E Student',
                    'status' => 'active',
                    'reg_no' => 'E2E001',
                    'admission_number' => 'E2E001',
                    'email' => 'student@e2e.test',
                    'user_id' => $studentUser->id,
                ]);
            } else {
                $student->update(['user_id' => $studentUser->id, 'email' => 'student@e2e.test']);
            }
        });

        $this->info('E2E users ready (password: '.$password.')');
        $this->table(['Role', 'Email', 'Tenant'], [
            ['superadmin', 'admin@sahodaya.test', 'central'],
            ['sahodaya_admin', 'sahodaya@malappuram.test', $sahodaya->id],
            ['sahodaya_staff', 'sahodaya_staff@e2e.test', $sahodaya->id],
            ['school_admin', 'admin@amu-school.test', $school->id],
            ['state_admin', 'state_admin@e2e.test', 'central'],
            ['judge', 'judge@e2e.test', $sahodaya->id],
            ['teacher', 'teacher@e2e.test', $school->id],
            ['student', 'student@e2e.test', $school->id],
            ['exam_controller', 'exam@e2e.test', $sahodaya->id],
            ['group_admin', 'group@e2e.test', $school->id],
            ['fest_ops', 'festops@e2e.test', $sahodaya->id],
            ['mark_entry_coordinator', 'mark_coordinator@e2e.test', $sahodaya->id],
            ['house_admin', 'house@e2e.test', $school->id],
        ]);

        return self::SUCCESS;
    }

    /** @param list<string> $roles */
    private function upsertUser(string $email, string $name, ?string $tenantId, array $roles, string $password): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'tenant_id' => $tenantId,
                'password' => Hash::make($password),
                'plain_password' => $password,
                'email_verified_at' => now(),
            ]
        );
        $user->syncRoles($roles);

        return $user;
    }
}
