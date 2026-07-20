<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillVerifiedEmails extends Command
{
    /**
     * php artisan users:backfill-verified-emails
     *
     * Before this fix, User::$fillable was missing 'email_verified_at', so every
     * account created via TenantUserProvisioner (Sahodaya/School "Users" screens)
     * or the Student/Teacher portal provisioners silently never got marked as
     * email-verified, even though the provisioning code intended to set it. Login
     * gates school_admin/school_principal/school_vice_principal accounts behind
     * hasVerifiedEmail() (see AuthController::login), so any such account created
     * before the fillable fix is permanently stuck at the "verify your email"
     * screen despite having a correct, working password.
     *
     * This command finds accounts with an email on file but no verification
     * timestamp, restricted to the affected admin-tier roles, and marks them
     * verified — mirroring what the provisioner always intended to do at
     * creation time. Safe to re-run; only touches rows that still need it.
     */
    protected $signature = 'users:backfill-verified-emails {--dry-run : List affected accounts without changing them}';

    protected $description = 'Mark pre-existing school_admin/principal/vice_principal accounts as email-verified (fixes accounts stuck before the email_verified_at fillable fix)';

    private const AFFECTED_ROLES = ['school_admin', 'school_principal', 'school_vice_principal'];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $totalFixed = 0;
        $totalTenants = 0;

        foreach (Tenant::all() as $tenant) {
            try {
                $tenant->run(function () use ($tenant, $dryRun, &$totalFixed, &$totalTenants) {
                    $users = User::query()
                        ->whereNotNull('email')
                        ->whereNull('email_verified_at')
                        ->whereHas('roles', fn ($q) => $q->whereIn('name', self::AFFECTED_ROLES))
                        ->get();

                    if ($users->isEmpty()) {
                        return;
                    }

                    $totalTenants++;

                    foreach ($users as $user) {
                        $this->line("  tenant {$tenant->getTenantKey()}: {$user->email} ({$user->name})".($dryRun ? ' [dry-run]' : ''));

                        if (! $dryRun) {
                            $user->forceFill(['email_verified_at' => now()])->save();
                        }

                        $totalFixed++;
                    }
                });
            } catch (\Throwable $e) {
                $this->error("  tenant {$tenant->getTenantKey()}: {$e->getMessage()}");
            }
        }

        $verb = $dryRun ? 'would be fixed' : 'fixed';
        $this->info("Done. {$totalFixed} account(s) across {$totalTenants} tenant(s) {$verb}.");

        return self::SUCCESS;
    }
}
