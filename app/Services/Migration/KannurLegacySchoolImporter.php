<?php

namespace App\Services\Migration;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\UserCredentialService;
use App\Support\SchoolApplicationForm;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class KannurLegacySchoolImporter
{
    public function __construct(
        private KannurLegacyMembershipImporter $importer,
        private UserCredentialService $credentials,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function import(
        Tenant $sahodaya,
        string $sqlPath,
        bool $dryRun = false,
        ?Command $output = null,
    ): array {
        $resolved = $this->importer->resolveSchoolMatches($sahodaya, $sqlPath);
        $usedPrefixes = $this->existingPrefixes($sahodaya);

        $stats = [
            'legacy_schools'      => $resolved['legacy_schools'],
            'already_present'     => count($resolved['matches']),
            'schools_created'     => 0,
            'logins_created'      => 0,
            'skipped_no_email'    => 0,
            'skipped_no_affiliation' => 0,
            'skipped_invalid'     => 0,
            'unmatched_remaining' => [],
        ];

        foreach ($resolved['unmatched'] as $row) {
            $legacy = $row['legacy'] ?? [];
            $legacyUser = $row['legacy_user'] ?? null;
            $name = trim((string) ($legacy['school_name'] ?? $row['legacy_name'] ?? ''));
            $email = $this->resolveEmail($legacy, $legacyUser);
            $affiliation = SchoolApplicationForm::normalizeAffiliation($legacy['affiliation_no'] ?? null);

            if ($name === '') {
                $stats['skipped_invalid']++;
                $this->line($output, "Skipped legacy user {$row['legacy_user_id']}: missing school name");

                continue;
            }

            if ($email === null) {
                $stats['skipped_no_email']++;
                $this->line($output, "Skipped {$name}: no valid login email");

                continue;
            }

            if ($affiliation === null) {
                $stats['skipped_no_affiliation']++;
                $this->line($output, "Skipped {$name}: missing CBSE affiliation");

                continue;
            }

            if (SchoolApplicationForm::affiliationIsTaken($sahodaya, $affiliation)) {
                $stats['already_present']++;
                $this->line($output, "○ {$name} — affiliation {$affiliation} already exists");

                continue;
            }

            $prefix = $this->allocatePrefix($affiliation, $name, (string) ($row['legacy_user_id'] ?? ''), $usedPrefixes);
            $payload = $this->buildPayload($legacy, $email, $affiliation, $prefix);

            if ($dryRun) {
                $stats['schools_created']++;
                $stats['logins_created']++;
                $this->line($output, "✓ {$name} — {$email} / {$affiliation} / {$prefix}");

                continue;
            }

            $school = Tenant::create([
                'id'                  => (string) Str::uuid(),
                'type'                => 'school',
                'name'                => $name,
                'parent_id'           => $sahodaya->id,
                'school_prefix'       => $prefix,
                'membership_status'   => 'pending',
                'is_active'           => true,
                'application_payload' => $payload,
            ]);

            TenancyDatabase::withTenantDatabase($sahodaya, function () use ($school, $email, &$stats, $output) {
                $user = User::create([
                    'tenant_id'            => $school->id,
                    'name'                 => $school->name,
                    'email'                => $email,
                    'password'             => Str::password(16),
                    'email_verified_at'    => now(),
                    'must_change_password' => true,
                ]);
                $user->assignRole('school_admin');

                $this->credentials->assignCredentials(
                    $user->fresh(),
                    password: $email,
                    mustChange: true,
                );

                $stats['logins_created']++;
            });

            $stats['schools_created']++;
            $this->line($output, "Created {$name} — {$email} (password = email)");
        }

        $after = $this->importer->resolveSchoolMatches($sahodaya, $sqlPath);
        $stats['unmatched_remaining'] = $after['unmatched'];

        return $stats;
    }

    /**
     * @param  array<string, string|null>  $legacySchool
     * @param  array<string, string|null>|null  $legacyUser
     */
    public function resolveEmail(array $legacySchool, ?array $legacyUser = null): ?string
    {
        $candidates = [
            strtolower(trim((string) ($legacySchool['email'] ?? ''))),
            strtolower(trim((string) ($legacyUser['email'] ?? ''))),
        ];

        foreach ($candidates as $email) {
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $legacySchool
     * @return array<string, mixed>
     */
    public function buildPayload(array $legacySchool, string $email, string $affiliation, string $prefix): array
    {
        $highestClass = $this->mapHighestClass($legacySchool['highest_class'] ?? null);

        return [
            'school_name'         => trim((string) ($legacySchool['school_name'] ?? '')),
            'school_email'        => $email,
            'contact_email'       => $email,
            'school_prefix'       => $prefix,
            'cbse_affiliation'    => $affiliation,
            'affiliation_number'  => $affiliation,
            'phone'               => trim((string) ($legacySchool['phone_no'] ?? '')),
            'contact_phone'       => trim((string) ($legacySchool['phone_no'] ?? '')),
            'website'             => trim((string) ($legacySchool['website'] ?? '')),
            'district'            => trim((string) ($legacySchool['district'] ?? '')),
            'highest_class'       => $highestClass,
            'legacy_imported'     => true,
            'legacy_user_id'      => (string) ($legacySchool['user_id'] ?? ''),
        ];
    }

    public function mapHighestClass(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $number = (int) $value;

            return $number >= 1 && $number <= 12 ? "Class {$number}" : null;
        }

        return $value;
    }

    /**
     * @return array<string, true>
     */
    private function existingPrefixes(Tenant $sahodaya): array
    {
        $prefixes = [];

        foreach (Tenant::query()->where('parent_id', $sahodaya->id)->where('type', 'school')->get() as $school) {
            $prefix = strtoupper(trim((string) $school->school_prefix));
            if ($prefix !== '') {
                $prefixes[$prefix] = true;
            }
        }

        return $prefixes;
    }

    /**
     * @param  array<string, true>  $usedPrefixes
     */
    public function allocatePrefix(string $affiliation, string $name, string $legacyUserId, array &$usedPrefixes): string
    {
        $candidates = [];

        $affSuffix = preg_replace('/[^A-Z0-9]/', '', strtoupper($affiliation));
        if (strlen($affSuffix) >= 4) {
            $candidates[] = substr($affSuffix, -6);
            $candidates[] = substr($affSuffix, -4);
        }

        $initials = collect(preg_split('/\s+/', strtoupper($name)) ?: [])
            ->filter()
            ->take(4)
            ->map(fn (string $word) => substr($word, 0, 1))
            ->implode('');
        if ($initials !== '') {
            $candidates[] = $initials;
        }

        $candidates[] = 'K'.substr(preg_replace('/\D/', '', $legacyUserId) ?: '00', -4);

        foreach ($candidates as $candidate) {
            $candidate = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($candidate)), 0, 10);
            if ($candidate === '') {
                continue;
            }

            $prefix = $this->firstAvailablePrefix($candidate, $usedPrefixes);
            $usedPrefixes[$prefix] = true;

            return $prefix;
        }

        $prefix = $this->firstAvailablePrefix('KS', $usedPrefixes);
        $usedPrefixes[$prefix] = true;

        return $prefix;
    }

    /**
     * @param  array<string, true>  $usedPrefixes
     */
    private function firstAvailablePrefix(string $base, array $usedPrefixes): string
    {
        $base = substr($base, 0, 8) ?: 'KS';

        if (! isset($usedPrefixes[$base])) {
            return $base;
        }

        for ($suffix = 2; $suffix <= 99; $suffix++) {
            $candidate = substr($base, 0, max(1, 10 - strlen((string) $suffix))).$suffix;
            if (! isset($usedPrefixes[$candidate])) {
                return $candidate;
            }
        }

        return $base.Str::upper(Str::random(2));
    }

    private function line(?Command $output, string $message): void
    {
        if ($output) {
            $output->line($message);
        }
    }
}
