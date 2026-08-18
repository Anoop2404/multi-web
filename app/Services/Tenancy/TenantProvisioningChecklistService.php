<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\TenantProvisioningChecklist;

class TenantProvisioningChecklistService
{
    /** @return array<string, string> step_key => label, in expected order, filtered to what applies to this tenant type. */
    public static function stepsFor(Tenant $tenant): array
    {
        $steps = ['tenant_created' => 'Tenant created'];

        if ($tenant->type === 'sahodaya') {
            $steps['database_configured'] = 'Database connection configured';
            $steps['database_migrated'] = 'Database migrated';
        }

        $steps['portal_admin_created'] = $tenant->type === 'school' ? 'School admin login created' : 'Sahodaya admin login created';
        $steps['logo_uploaded'] = 'Logo uploaded';

        return $steps;
    }

    public function markComplete(Tenant $tenant, string $stepKey, ?int $userId = null): void
    {
        TenantProvisioningChecklist::updateOrCreate(
            ['tenant_id' => $tenant->id, 'step_key' => $stepKey],
            ['completed_at' => now(), 'completed_by_user_id' => $userId]
        );
    }

    /** @return array{steps: array<string, array{label: string, completed: bool, completed_at: ?string}>, complete: bool, pending_count: int} */
    public function statusFor(Tenant $tenant): array
    {
        $expected = self::stepsFor($tenant);
        $done = TenantProvisioningChecklist::where('tenant_id', $tenant->id)
            ->whereIn('step_key', array_keys($expected))
            ->get()
            ->keyBy('step_key');

        $steps = [];
        foreach ($expected as $key => $label) {
            $row = $done->get($key);
            $steps[$key] = [
                'label' => $label,
                'completed' => $row !== null,
                'completed_at' => $row?->completed_at?->toIso8601String(),
            ];
        }

        $pendingCount = count(array_filter($steps, fn ($s) => ! $s['completed']));

        return [
            'steps' => $steps,
            'complete' => $pendingCount === 0,
            'pending_count' => $pendingCount,
        ];
    }
}
