<?php

namespace App\Services\State;

use App\Models\ExternalSahodaya;
use App\Models\State\StateSahodaya;
use App\Models\Tenant;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Phase 1 of the State Kalotsav module — the shared Sahodaya directory.
 *
 * Every State operational row must hang off ONE canonical Sahodaya identity, whether that Sahodaya
 * runs on the platform or not, and that identity must not change when an outside Sahodaya is later
 * promoted to a tenant. This service is the only place that decides what that identity is.
 *
 * The raw key a submission arrives with is polymorphic — a central tenant uuid for a managed
 * Sahodaya, "external:{uuid}" for one that is not on the platform (see
 * ExternalIntakeService::openDraftIntake). resolve() maps either form onto a directory row; on
 * promotion, linkPromotedTenant() attaches the new tenant to the SAME row, so a Sahodaya's history
 * and its later submissions stay a single body in standings, slot usage, fees and reports rather
 * than becoming two.
 *
 * Names are snapshotted onto the directory row rather than read live from the central tables: the
 * State module is not allowed to depend on tenant databases for its own reporting, and a Sahodaya
 * renamed or removed after an event must not silently rewrite that event's history.
 */
class StateSahodayaDirectory
{
    /** The prefix an outside Sahodaya's intakes are keyed with. */
    public const EXTERNAL_PREFIX = 'external:';

    public static function isExternalKey(?string $sourceKey): bool
    {
        return $sourceKey !== null && str_starts_with($sourceKey, self::EXTERNAL_PREFIX);
    }

    public static function externalIdFromKey(string $sourceKey): string
    {
        return substr($sourceKey, strlen(self::EXTERNAL_PREFIX));
    }

    /**
     * Canonical identity for whatever a submission was keyed with. Creates the directory row the
     * first time a Sahodaya takes part, and returns the existing one after that.
     */
    public function resolve(string $sourceKey, ?string $stateId = null): StateSahodaya
    {
        if (self::isExternalKey($sourceKey)) {
            $external = ExternalSahodaya::find(self::externalIdFromKey($sourceKey));

            if (! $external) {
                throw new RuntimeException("No outside Sahodaya matches \"{$sourceKey}\".");
            }

            return $this->forExternal($external, $stateId);
        }

        $tenant = Tenant::query()->where('type', 'sahodaya')->find($sourceKey);

        if (! $tenant) {
            throw new RuntimeException("No Sahodaya tenant matches \"{$sourceKey}\".");
        }

        return $this->forTenant($tenant, $stateId);
    }

    /** Same as resolve(), but returns null instead of throwing on an unresolvable key. */
    public function resolveOrNull(string $sourceKey, ?string $stateId = null): ?StateSahodaya
    {
        try {
            return $this->resolve($sourceKey, $stateId);
        } catch (RuntimeException) {
            return null;
        }
    }

    public function forTenant(Tenant $tenant, ?string $stateId = null): StateSahodaya
    {
        $stateId ??= $tenant->state_id;

        // A tenant that was promoted from the outside intake already has a directory row under its
        // external origin — find it by that link first, or promotion would fork the identity here
        // instead of at submission time.
        $existing = StateSahodaya::query()
            ->where('state_id', $stateId)
            ->where(function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id)
                    ->orWhereIn('external_sahodaya_id', ExternalSahodaya::where('tenant_id', $tenant->id)->pluck('id'));
            })
            ->first();

        if ($existing) {
            $existing->fill(array_filter([
                'tenant_id' => $tenant->id,
                'name'      => $existing->name ?: $tenant->name,
            ]))->save();

            return $existing->fresh();
        }

        return StateSahodaya::create([
            'id'        => (string) Str::uuid(),
            'state_id'  => $stateId,
            'name'      => $tenant->name,
            'code'      => $this->codeFor($tenant->name),
            'tenant_id' => $tenant->id,
            'origin'    => StateSahodaya::ORIGIN_MANAGED,
            'is_active' => (bool) $tenant->is_active,
        ]);
    }

    public function forExternal(ExternalSahodaya $external, ?string $stateId = null): StateSahodaya
    {
        $stateId ??= $external->state_id ?? $external->program?->state_id;

        $existing = StateSahodaya::query()
            ->where('state_id', $stateId)
            ->where('external_sahodaya_id', $external->id)
            ->first();

        if ($existing) {
            // An already-promoted outside Sahodaya keeps its one row and gains the tenant link.
            if ($external->tenant_id && ! $existing->tenant_id) {
                $existing->forceFill(['tenant_id' => $external->tenant_id, 'promoted_at' => now()])->save();
            }

            return $existing->fresh();
        }

        return StateSahodaya::create([
            'id'                   => (string) Str::uuid(),
            'state_id'             => $stateId,
            'name'                 => $external->name,
            'code'                 => $this->codeFor($external->name),
            'district'             => $external->district,
            'external_sahodaya_id' => $external->id,
            'tenant_id'            => $external->tenant_id,
            'origin'               => StateSahodaya::ORIGIN_EXTERNAL,
            'is_active'            => $external->isActive(),
            'promoted_at'          => $external->tenant_id ? now() : null,
        ]);
    }

    /**
     * Called when an outside Sahodaya is promoted to a tenant. Attaches the tenant to the identity
     * that already holds its history instead of letting a second one appear the next time it
     * submits — the de-duplication the whole directory exists for.
     *
     * If a separate managed row somehow already exists for that tenant (a Sahodaya that submitted
     * both ways before the directory existed), the two are merged onto the external-origin row,
     * which is the one carrying the earlier history.
     */
    public function linkPromotedTenant(ExternalSahodaya $external, Tenant $tenant): ?StateSahodaya
    {
        $stateId = $tenant->state_id ?? $external->state_id ?? $external->program?->state_id;

        $row = StateSahodaya::query()
            ->where('state_id', $stateId)
            ->where('external_sahodaya_id', $external->id)
            ->first();

        if (! $row) {
            // It has never submitted anything, so there is no history to keep single. The directory
            // row will be created with both links the first time it does.
            return null;
        }

        $duplicate = StateSahodaya::query()
            ->where('state_id', $stateId)
            ->where('tenant_id', $tenant->id)
            ->where('id', '!=', $row->id)
            ->first();

        if ($duplicate) {
            $this->absorb($duplicate, $row);
        }

        $row->forceFill([
            'tenant_id'   => $tenant->id,
            'name'        => $row->name ?: $tenant->name,
            'promoted_at' => $row->promoted_at ?? now(),
        ])->save();

        return $row->fresh();
    }

    /** Move everything pointing at $from onto $into, then drop $from. */
    private function absorb(StateSahodaya $from, StateSahodaya $into): void
    {
        \App\Models\State\StateQualifierIntake::where('sahodaya_id', $from->id)
            ->update(['sahodaya_id' => $into->id]);

        \App\Models\State\StateFestRegistration::where('sahodaya_id', $from->id)
            ->update(['sahodaya_id' => $into->id]);

        $from->delete();
    }

    /**
     * The directory for a state: every Sahodaya that has taken part, managed and outside alike.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, StateSahodaya>
     */
    public function forStateListing(?string $stateId): \Illuminate\Database\Eloquent\Collection
    {
        return StateSahodaya::query()->forState($stateId)->orderBy('district')->orderBy('name')->get();
    }

    /** Canonical ids keyed by every raw source key that resolves to them. @return array<string, string> */
    public function sourceKeyMap(?string $stateId): array
    {
        $map = [];

        foreach (StateSahodaya::query()->forState($stateId)->get() as $sahodaya) {
            foreach ($sahodaya->sourceKeys() as $key) {
                $map[$key] = $sahodaya->id;
            }
        }

        return $map;
    }

    private function codeFor(string $name): string
    {
        $cleaned = preg_replace('/\b(sahodaya|sahodayas|school complex|complex|cbse)\b/i', ' ', $name) ?? $name;

        return Str::upper(Str::limit(Str::slug($cleaned, ''), 12, ''));
    }
}
