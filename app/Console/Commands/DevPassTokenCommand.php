<?php

namespace App\Console\Commands;

use App\Models\PlatformUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DevPassTokenCommand extends Command
{
    protected $signature = 'dev:pass-token
                            {username? : Target username or email to verify login for}
                            {--set-token= : Set or generate a new DEV_LOGIN_PASS_TOKEN in .env}';

    protected $description = 'Inspect, test, or configure the Developer Login Pass Token';

    public function handle(): int
    {
        $newToken = $this->option('set-token');

        if ($newToken !== null) {
            $tokenToSet = $newToken === '' ? Str::random(24) : $newToken;
            $this->setEnvToken($tokenToSet);
            $this->info("Updated DEV_LOGIN_PASS_TOKEN in .env to: {$tokenToSet}");
        }

        $activeToken = config('auth.dev_pass_token') ?: env('DEV_LOGIN_PASS_TOKEN');

        if (empty($activeToken)) {
            $this->warn('DEV_LOGIN_PASS_TOKEN is currently NOT set.');
            $this->line('Run `php artisan dev:pass-token --set-token=YOUR_PASS_TOKEN` to configure one.');

            return self::SUCCESS;
        }

        $this->info('Developer Login Pass Token Details:');
        $this->line("  Active Token: {$activeToken}");
        $this->line('');

        $target = $this->argument('username');

        if ($target) {
            $field = str_contains($target, '@') ? 'email' : 'username';
            $user = User::where($field, $target)->first();
            $platformUser = PlatformUser::where($field, $target)->first();

            if (! $user && ! $platformUser) {
                $this->error("No user found with {$field} [{$target}].");

                return self::FAILURE;
            }

            $matchedUser = $user ?? $platformUser;
            $userType = $user ? 'User (Tenant)' : 'PlatformUser (Superadmin)';

            $this->info('Target Account Verified:');
            $this->line("  ID:       {$matchedUser->id}");
            $this->line("  Type:     {$userType}");
            $this->line("  Name:     {$matchedUser->name}");
            $this->line("  Email:    {$matchedUser->email}");
            $this->line("  Username: {$matchedUser->username}");
            $this->line('');
            $this->info('Login Instruction:');
            $this->line("  Login with Username/Email: {$target}");
            $this->line("  Password:                  {$activeToken}");
        } else {
            $this->line('Usage:');
            $this->line('  Enter any valid Username/Email on the login screen.');
            $this->line("  Use Password: {$activeToken}");
        }

        return self::SUCCESS;
    }

    private function setEnvToken(string $token): void
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);
        if (preg_match('/^DEV_LOGIN_PASS_TOKEN=.*/m', $content)) {
            $content = preg_replace('/^DEV_LOGIN_PASS_TOKEN=.*/m', "DEV_LOGIN_PASS_TOKEN={$token}", $content);
        } else {
            $content .= "\nDEV_LOGIN_PASS_TOKEN={$token}\n";
        }

        file_put_contents($envPath, $content);
    }
}
