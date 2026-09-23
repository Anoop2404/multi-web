<?php

namespace App\Console\Commands\State;

use App\Models\ExternalSahodaya;
use App\Models\FestStateProgram;
use App\Models\PlatformState;
use App\Services\State\SahodayaPromotionService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Throwable;

/**
 * Phase 2 of docs/STATE_ADMIN_TENANT_PROMOTION_AND_UAT_PLAN_2026_09_23.md.
 *
 * Promotes rows on the seeded state Sahodaya master list (created by state:seed-external-sahodayas)
 * into real tenants — subdomain + dedicated database. The companion of that seeder: one fills the
 * list, this one graduates rows off it.
 *
 * Run --dry-run first, every time. It is the only place subdomain collisions and duplicate names in
 * the official list become visible before a Postgres database gets created for them.
 *
 * One bad row never aborts the batch: each Sahodaya is promoted independently and a failure is
 * recorded on the row (promotion_status=failed + promotion_error) for retry, which is what makes
 * promoting a whole district in one go safe.
 */
class PromoteSahodayas extends Command
{
    protected $signature = 'state:promote-sahodayas
        {--state-program= : Only Sahodayas attached to this FestStateProgram uuid}
        {--sahodaya=*     : Specific ExternalSahodaya uuids}
        {--district=      : Only this district (case-insensitive)}
        {--limit=         : Stop after this many Sahodayas}
        {--plan=standard  : Subscription plan to set on the new tenants}
        {--state=         : State code (e.g. KL) or uuid to stamp on the new tenants}
        {--retry-failed   : Include (and resume) rows whose last promotion failed}
        {--no-create-db   : Do not create the Postgres database; fail if it is missing}
        {--dry-run        : Print what would happen and exit without writing anything}';

    protected $description = 'Promote outside-Sahodaya master-list rows into real tenants with a subdomain and dedicated database';

    public function handle(SahodayaPromotionService $promotions): int
    {
        $stateId = $this->resolveStateId();
        if ($stateId === false) {
            return Command::FAILURE;
        }

        $query = ExternalSahodaya::query()->orderBy('district')->orderBy('name');

        if ($programId = $this->option('state-program')) {
            if (! FestStateProgram::find($programId)) {
                $this->error('No FestStateProgram found with that id.');

                return Command::FAILURE;
            }
            $query->where('state_program_id', $programId);
        }

        if ($ids = array_filter((array) $this->option('sahodaya'))) {
            $query->whereIn('id', $ids);
        }

        if ($district = $this->option('district')) {
            $query->whereRaw('lower(district) = ?', [strtolower(trim($district))]);
        }

        // Appeal-pool rows are never promotable (they are a synthetic entry pool, not a body with
        // schools), so they are filtered out here rather than reported as failures for every run.
        $query->where('is_appeal_pool', false);

        $query->where(function (Builder $q) {
            $q->whereNull('tenant_id');

            if ($this->option('retry-failed')) {
                $q->orWhere('promotion_status', ExternalSahodaya::PROMOTION_FAILED);
            }
        });

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, ExternalSahodaya> $rows */
        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to promote for that selection.');

            return Command::SUCCESS;
        }

        return $this->option('dry-run')
            ? $this->printPlan($rows, $promotions, $stateId)
            : $this->promoteRows($rows, $promotions, $stateId);
    }

    /**
     * Resolves --state into a uuid. Returns false on a bad value (the caller turns that into a
     * failure exit), null when the option was not given.
     *
     * Worth being explicit rather than clever here: most seeded FestStateProgram rows still have a
     * null state_id (they predate multi-state), so without this option the new tenants inherit null
     * and will not appear in any state-scoped listing. Guessing "there is only one state, use it"
     * would quietly do the wrong thing the day a second state exists.
     */
    private function resolveStateId(): string|false|null
    {
        $value = $this->option('state');

        if (blank($value)) {
            return null;
        }

        $value = trim($value);

        // states.id is a real Postgres uuid column, so comparing a code like "KL" against it is a
        // datatype error, not a miss — only try the id branch when the value could be one.
        $state = PlatformState::query()
            ->when(
                Str::isUuid($value),
                fn ($q) => $q->where('id', $value),
                fn ($q) => $q->whereRaw('upper(code) = ?', [strtoupper($value)]),
            )
            ->first();

        if (! $state) {
            $known = PlatformState::query()->pluck('code')->implode(', ');
            $this->error("No state matches \"{$value}\".".($known ? " Known codes: {$known}" : ''));

            return false;
        }

        return $state->id;
    }

    private function printPlan($rows, SahodayaPromotionService $promotions, ?string $stateId): int
    {
        $this->info("Dry run — {$rows->count()} Sahodaya(s) selected. Nothing will be written.");
        if ($stateId === null) {
            $this->warn('No --state given: the new tenants will have a null state_id and will not '
                .'appear in state-scoped listings. Pass --state=<code> unless that is intended.');
        }
        $this->newLine();

        // Subdomains claimed within this plan are tracked so the preview shows the same distinct
        // hosts a real run would produce, instead of proposing one host three times.
        $claimed = [];
        $table = [];
        $blocked = 0;

        foreach ($rows as $row) {
            $plan = $promotions->plan($row, $claimed);

            if ($plan['promotable']) {
                $claimed[] = $plan['subdomain'];
            } else {
                $blocked++;
            }

            $table[] = [
                $row->district ?: '—',
                $row->name,
                $plan['promotable'] ? $plan['subdomain'] : '—',
                $plan['admin_email'] ?: 'no contact email',
                (string) $plan['schools'],
                $plan['promotable'] ? 'ready' : $plan['reason'],
            ];
        }

        $this->table(['District', 'Sahodaya', 'Subdomain', 'Admin login', 'Schools', 'Status'], $table);

        $ready = $rows->count() - $blocked;
        $this->info("{$ready} ready to promote, {$blocked} blocked.");
        $this->line('Re-run without --dry-run to promote. Schools are migrated separately (Phase 3).');

        return Command::SUCCESS;
    }

    private function promoteRows($rows, SahodayaPromotionService $promotions, ?string $stateId): int
    {
        $opts = array_filter([
            'plan'            => (string) $this->option('plan'),
            'create_database' => ! $this->option('no-create-db'),
            'state_id'        => $stateId,
        ], fn ($v) => $v !== null);

        $promoted = 0;
        $failed = [];

        foreach ($rows as $row) {
            $this->newLine();
            $this->line("<options=bold>{$row->name}</>".($row->district ? " ({$row->district})" : ''));

            try {
                $tenant = $row->isPromoted()
                    ? $promotions->retry($row, $opts, fn ($k, $label) => $this->line("  ✓ {$label}"))
                    : $promotions->promote($row, $opts, fn ($k, $label) => $this->line("  ✓ {$label}"));

                $promoted++;
                $this->info('  → '.\App\Support\TenantDomainSync::publicUrl($tenant));
            } catch (Throwable $e) {
                $failed[$row->name] = $e->getMessage();
                $this->error('  ✗ '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Promoted {$promoted} of {$rows->count()}.");

        if ($failed) {
            $this->newLine();
            $this->error('Failed:');
            foreach ($failed as $name => $message) {
                $this->line("  - {$name}: {$message}");
            }
            $this->line('Fix the cause and re-run with --retry-failed to resume those rows.');

            return Command::FAILURE;
        }

        $this->line('Next: php artisan state:migrate-external-schools --all-promoted (Phase 3).');

        return Command::SUCCESS;
    }
}
