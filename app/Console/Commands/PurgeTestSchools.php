<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenancy\SchoolDataPurger;
use Illuminate\Console\Command;

class PurgeTestSchools extends Command
{
    protected $signature = 'schools:purge-test
                            {--sahodaya= : Sahodaya tenant ID or subdomain}
                            {--pattern= : Delete schools whose name contains this (case-insensitive)}
                            {--status= : Delete schools whose membership_status is in this comma-separated list (e.g. approved,rejected)}
                            {--school= : Purge one school by ID}
                            {--dry-run : List matches without deleting}
                            {--force : Delete without confirmation}';

    protected $description = 'Delete test schools and all related data from a Sahodaya tenant';

    public function handle(SchoolDataPurger $purger): int
    {
        if (! $this->option('school') && ! $this->option('pattern') && ! $this->option('status')) {
            $this->error('Refusing to run with no filter — pass --school=, --pattern=, and/or --status= so this cannot accidentally match every school in the Sahodaya.');

            return self::FAILURE;
        }

        $sahodaya = $this->resolveSahodaya();
        if (! $sahodaya) {
            $this->error('Sahodaya tenant not found.');

            return self::FAILURE;
        }

        $schools = $this->resolveSchools($sahodaya);
        if ($schools->isEmpty()) {
            $this->warn('No matching test schools found.');

            return self::SUCCESS;
        }

        $this->info("Sahodaya: {$sahodaya->name} ({$sahodaya->id})");
        $this->table(
            ['ID', 'Name', 'Prefix', 'Status', 'Created'],
            $schools->map(fn (Tenant $school) => [
                $school->id,
                $school->name,
                $school->school_prefix ?: '—',
                $school->membership_status,
                $school->created_at?->toDateTimeString(),
            ])->all(),
        );

        if ($this->option('dry-run')) {
            $this->comment('Dry run only — nothing deleted.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Permanently delete '.$schools->count().' school(s) and all related data?', false)) {
            $this->comment('Cancelled.');

            return self::SUCCESS;
        }

        foreach ($schools as $school) {
            $this->line("Purging {$school->name} ({$school->id})…");
            $result = $purger->purge($school);
            $deleted = array_sum($result['tenant']);
            $this->info("  Removed {$deleted} tenant rows, {$result['users']} user(s)".($result['storage_removed'] ? ', storage folder' : '').'.');
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function resolveSahodaya(): ?Tenant
    {
        $key = $this->option('sahodaya');

        if ($key) {
            return Tenant::query()
                ->where('type', 'sahodaya')
                ->where(function ($query) use ($key) {
                    $query->where('id', $key)->orWhere('subdomain', $key);
                })
                ->first();
        }

        return Tenant::query()->where('type', 'sahodaya')->orderBy('created_at')->first();
    }

    /** @return \Illuminate\Support\Collection<int, Tenant> */
    private function resolveSchools(Tenant $sahodaya)
    {
        $query = Tenant::query()
            ->where('type', 'school')
            ->where('parent_id', $sahodaya->id);

        if ($schoolId = $this->option('school')) {
            return $query->where('id', $schoolId)->get();
        }

        $pattern = trim((string) $this->option('pattern'));
        if ($pattern !== '') {
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($pattern).'%']);
        }

        $status = trim((string) $this->option('status'));
        if ($status !== '') {
            $statuses = array_values(array_filter(array_map('trim', explode(',', $status))));
            $allowed = ['pending', 'approved', 'rejected'];
            $invalid = array_diff($statuses, $allowed);
            if ($invalid !== []) {
                $this->error('Invalid --status value(s): '.implode(', ', $invalid).'. Allowed: '.implode(', ', $allowed));

                return collect();
            }
            $query->whereIn('membership_status', $statuses);
        }

        return $query->orderBy('created_at')->get();
    }
}
