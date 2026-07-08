<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\LoginCodeGenerator;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class BackfillLoginCodes extends Command
{
    protected $signature = 'erp:backfill-login-codes
                            {--tenant= : Sahodaya tenant id}
                            {--no-sync-usernames : Do not sync portal user usernames to login codes}';

    protected $description = 'Assign T/YY/0001 login codes (per-Sahodaya, per-year) to teachers and sync portal usernames';

    public function handle(LoginCodeGenerator $generator): int
    {
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $tenant = Tenant::query()->where('type', 'sahodaya')->findOrFail($tenantId);
            $this->backfillTenant($tenant, $generator);

            return self::SUCCESS;
        }

        $count = 0;
        Tenant::query()->where('type', 'sahodaya')->each(function (Tenant $tenant) use ($generator, &$count) {
            $this->backfillTenant($tenant, $generator);
            $count++;
        });

        $this->info("Processed {$count} Sahodaya tenant(s).");

        return self::SUCCESS;
    }

    private function backfillTenant(Tenant $sahodaya, LoginCodeGenerator $generator): void
    {
        $this->info("Backfilling teacher login codes for {$sahodaya->name} ({$sahodaya->id})");

        TenancyDatabase::withTenantDatabase($sahodaya, function () use ($generator) {
            $teachers = Teacher::query()->orderBy('id')->get();
            $assigned = 0;

            foreach ($teachers as $teacher) {
                $before = $teacher->login_code;
                $code = $generator->assignTeacher($teacher);
                $this->syncUsername($teacher->user_id, $code);

                if ($before !== $code) {
                    $assigned++;
                    $this->line("  Teacher {$teacher->id} → {$code}");
                }
            }

            $this->info("  Assigned/updated {$assigned} teacher code(s).");
            $this->line('  Students: use erp:backfill-student-reg-numbers (reg_no is the only student ID).');
        });
    }

    private function syncUsername(?int $userId, string $loginCode): void
    {
        if ($this->option('no-sync-usernames') || ! $userId) {
            return;
        }

        User::whereKey($userId)->update(['username' => $loginCode]);
    }
}
