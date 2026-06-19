<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class EnsurePortalAdmin extends Command
{
    protected $signature = 'portal:ensure-admin
                            {--email= : Admin login email}
                            {--password= : Plain-text password to set}
                            {--domain=malappuramcentralsahodaya.org : Sahodaya tenant custom domain}
                            {--name=Malappuram Sahodaya Admin : Display name}';

    protected $description = 'Create or reset a Sahodaya admin account for the mobile and web portal';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->option('email')));
        $password = (string) $this->option('password');
        $domain = strtolower(trim((string) $this->option('domain')));
        $name = (string) $this->option('name');

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide a valid --email.');

            return self::FAILURE;
        }

        if (strlen($password) < 8) {
            $this->error('Provide --password with at least 8 characters.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            $this->error('Missing personal_access_tokens table. Run: php artisan migrate');

            return self::FAILURE;
        }

        $this->callSilent('db:seed', ['--class' => RolesAndPermissionsSeeder::class]);

        $tenant = Tenant::query()
            ->where('type', 'sahodaya')
            ->where('domain', $domain)
            ->first()
            ?? Tenant::query()
                ->where('type', 'sahodaya')
                ->where('subdomain', 'malappuram')
                ->first();

        if (! $tenant) {
            $this->error("No Sahodaya tenant found for domain [{$domain}] or subdomain [malappuram].");

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user && $user->tenant_id && $user->tenant_id !== $tenant->id) {
            $this->error("Email [{$email}] already belongs to another tenant.");

            return self::FAILURE;
        }

        $user ??= new User(['tenant_id' => $tenant->id]);

        $user->fill([
            'tenant_id'         => $tenant->id,
            'name'              => $name,
            'email'             => $email,
            'password'          => $password,
            'plain_password'    => $password,
            'email_verified_at' => now(),
        ]);
        $user->save();
        $user->syncRoles(['sahodaya_admin']);

        $passwordOk = Hash::check($password, $user->fresh()->password);

        $this->info('Portal admin ready.');
        $this->line("  Tenant: {$tenant->name} ({$tenant->id})");
        $this->line("  Email:  {$email}");
        $this->line('  Role:   sahodaya_admin');
        $this->line('  Password check: '.($passwordOk ? 'OK' : 'FAILED'));

        return $passwordOk ? self::SUCCESS : self::FAILURE;
    }
}
