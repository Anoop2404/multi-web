<?php

namespace App\Services\Migration;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\UserCredentialService;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class KannurLegacySchoolCredentialSync
{
    public function __construct(
        private KannurLegacyMembershipImporter $importer,
        private UserCredentialService $credentials,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function sync(
        Tenant $sahodaya,
        string $sqlPath,
        bool $dryRun = false,
        bool $createMissing = false,
        ?Command $output = null,
    ): array {
        $resolved = $this->importer->resolveSchoolMatches($sahodaya, $sqlPath);

        $stats = [
            'legacy_schools'   => $resolved['legacy_schools'],
            'matched_schools'  => count($resolved['matches']),
            'passwords_reset'  => 0,
            'logins_created'   => 0,
            'skipped_no_email' => 0,
            'skipped_no_login' => 0,
            'unmatched'        => $resolved['unmatched'],
        ];

        if ($dryRun) {
            foreach ($resolved['matches'] as $match) {
                $email = $this->resolveLoginEmail($match['school'], $match['legacy'], $match['legacy_user']);
                if ($email === null) {
                    $stats['skipped_no_email']++;
                    $this->line($output, "✗ {$match['school']->name} — no Gmail login email");

                    continue;
                }

                $hasLogin = $this->schoolHasAdminLogin($match['school']);
                if (! $hasLogin && ! $createMissing) {
                    $stats['skipped_no_login']++;
                    $this->line($output, "○ {$match['school']->name} — {$email} (no login; use --create-missing)");

                    continue;
                }

                $action = $hasLogin ? 'reset password' : 'create login';
                $this->line($output, "✓ {$match['school']->name} — {$email} ({$action})");
                $stats[$hasLogin ? 'passwords_reset' : 'logins_created']++;
            }

            return $stats;
        }

        TenancyDatabase::withTenantDatabase($sahodaya, function () use (
            $resolved,
            $createMissing,
            $output,
            &$stats,
        ) {
            foreach ($resolved['matches'] as $match) {
                $school = $match['school'];
                $email = $this->resolveLoginEmail($school, $match['legacy'], $match['legacy_user']);

                if ($email === null) {
                    $stats['skipped_no_email']++;
                    $this->line($output, "Skipped {$school->name}: no Gmail login email");

                    continue;
                }

                $user = $this->findSchoolAdmin($school);

                if (! $user) {
                    if (! $createMissing) {
                        $stats['skipped_no_login']++;
                        $this->line($output, "Skipped {$school->name}: no school login (use --create-missing)");

                        continue;
                    }

                    $user = $this->createSchoolAdmin($school, $email);
                    $stats['logins_created']++;
                } else {
                    $stats['passwords_reset']++;
                }

                if (strtolower((string) $user->email) !== $email) {
                    $user->forceFill(['email' => $email])->save();
                }

                $this->credentials->assignCredentials(
                    $user->fresh(),
                    password: $email,
                    mustChange: true,
                );

                $this->line($output, "Updated {$school->name}: login {$email}, password = email");
            }
        });

        return $stats;
    }

    /**
     * @param  array<string, string|null>  $legacySchool
     * @param  array<string, string|null>|null  $legacyUser
     */
    public function resolveLoginEmail(Tenant $school, array $legacySchool, ?array $legacyUser = null): ?string
    {
        $candidates = [
            $this->schoolPayloadEmail($school),
            strtolower(trim((string) ($legacyUser['email'] ?? ''))),
            strtolower(trim((string) ($legacySchool['email'] ?? ''))),
            strtolower(trim((string) ($school->email ?? ''))),
        ];

        foreach ($candidates as $email) {
            if ($this->isGmailLoginEmail($email)) {
                return $email;
            }
        }

        return null;
    }

    private function schoolPayloadEmail(Tenant $school): string
    {
        $payload = is_array($school->application_payload) ? $school->application_payload : [];

        return strtolower(trim((string) ($payload['school_email'] ?? '')));
    }

    public function isGmailLoginEmail(?string $email): bool
    {
        $email = strtolower(trim((string) $email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return str_ends_with($email, '@gmail.com');
    }

    private function schoolHasAdminLogin(Tenant $school): bool
    {
        return $this->findSchoolAdmin($school) !== null;
    }

    private function findSchoolAdmin(Tenant $school): ?User
    {
        $user = User::role('school_admin')->where('tenant_id', $school->id)->first();
        if ($user) {
            return $user;
        }

        return User::role(['school_principal', 'school_vice_principal'])
            ->where('tenant_id', $school->id)
            ->first();
    }

    private function createSchoolAdmin(Tenant $school, string $email): User
    {
        $user = User::create([
            'tenant_id' => $school->id,
            'name'      => $school->name,
            'email'     => $email,
            'password'  => Str::password(16),
        ]);

        $user->assignRole('school_admin');

        return $user->fresh();
    }

    private function line(?Command $output, string $message): void
    {
        if ($output) {
            $output->line($message);
        }
    }
}
