<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Services\Mcq\McqConcurrencyLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class McqConcurrencySettingsController extends Controller
{
    public function index(Request $request, McqConcurrencyLimitService $service): Response
    {
        $global = $service->globalSettings();
        $live = $service->liveStats();

        $cutoff = now()->subMinutes(180);

        // Active writing count per Sahodaya
        $writingCountsBySahodaya = McqRegistration::query()
            ->join('tenants as schools', 'schools.id', '=', 'mcq_registrations.school_id')
            ->where('mcq_registrations.status', 'started')
            ->whereNotNull('mcq_registrations.started_at')
            ->where('mcq_registrations.started_at', '>=', $cutoff)
            ->groupBy('schools.parent_id')
            ->selectRaw('schools.parent_id as sahodaya_id, count(*) as total')
            ->pluck('total', 'sahodaya_id')
            ->all();

        $sahodayas = Tenant::query()
            ->where('type', 'sahodaya')
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(function (Tenant $sahodaya) use ($service, $global, $writingCountsBySahodaya) {
                $override = $service->overrideForTenant($sahodaya->id);

                return [
                    'id'                          => $sahodaya->id,
                    'name'                        => $sahodaya->name,
                    'code'                        => $sahodaya->code,
                    'has_override'                => $override !== null,
                    'override'                    => $override,
                    'effective_school_limit'      => $override['max_concurrent_per_school'] ?? $global['max_concurrent_per_school'],
                    'effective_sahodaya_limit'    => $override['max_concurrent_per_sahodaya'] ?? $global['max_concurrent_per_sahodaya'],
                    'active_writing_count'        => (int) ($writingCountsBySahodaya[$sahodaya->id] ?? 0),
                ];
            })
            ->values();

        // Schools with custom overrides
        $overrideTenantIds = \App\Models\TenantSetting::where('key', McqConcurrencyLimitService::SETTING_KEY)
            ->where('tenant_id', '!=', McqConcurrencyLimitService::PLATFORM_TENANT_ID)
            ->pluck('tenant_id');

        $schoolOverrides = Tenant::query()
            ->where('type', 'school')
            ->whereIn('id', $overrideTenantIds)
            ->with('parent:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'parent_id'])
            ->map(function (Tenant $school) use ($service) {
                $override = $service->overrideForTenant($school->id);

                return [
                    'id'            => $school->id,
                    'name'          => $school->name,
                    'code'          => $school->code,
                    'parent_name'   => $school->parent?->name ?? '—',
                    'override'      => $override,
                ];
            })
            ->values();

        return inertia('Admin/Mcq/Settings', [
            'globalSettings'  => $global,
            'liveStats'       => $live,
            'sahodayas'       => $sahodayas,
            'schoolOverrides' => $schoolOverrides,
        ]);
    }

    public function updateGlobal(Request $request, McqConcurrencyLimitService $service): RedirectResponse
    {
        $validated = $request->validate([
            'enabled'                     => 'required|boolean',
            'max_concurrent_per_school'   => 'nullable|integer|min:1|max:100000',
            'max_concurrent_per_sahodaya' => 'nullable|integer|min:1|max:100000',
            'queue_message_school'        => 'nullable|string|max:255',
            'queue_message_sahodaya'      => 'nullable|string|max:255',
        ]);

        $updated = $service->saveGlobalSettings($validated);

        AuditLog::create([
            'tenant_id'   => null,
            'user_id'     => $request->user()?->id,
            'action'      => 'platform.mcq.concurrency_limits.updated',
            'description' => 'Updated platform-wide MCQ exam concurrency limits',
            'details'     => $updated,
        ]);

        return back()->with('success', 'Global exam concurrency limits updated successfully.');
    }

    public function updateOverride(Request $request, Tenant $tenant, McqConcurrencyLimitService $service): RedirectResponse
    {
        $validated = $request->validate([
            'max_concurrent_per_school'   => 'nullable|integer|min:1|max:100000',
            'max_concurrent_per_sahodaya' => 'nullable|integer|min:1|max:100000',
        ]);

        $service->saveTenantOverride($tenant->id, $validated);

        AuditLog::create([
            'tenant_id'   => $tenant->id,
            'user_id'     => $request->user()?->id,
            'action'      => 'platform.mcq.concurrency_override.saved',
            'description' => "Updated custom concurrency limits for {$tenant->name}",
            'details'     => $validated,
        ]);

        return back()->with('success', "Custom concurrency limits saved for {$tenant->name}.");
    }

    public function deleteOverride(Request $request, Tenant $tenant, McqConcurrencyLimitService $service): RedirectResponse
    {
        $service->deleteTenantOverride($tenant->id);

        AuditLog::create([
            'tenant_id'   => $tenant->id,
            'user_id'     => $request->user()?->id,
            'action'      => 'platform.mcq.concurrency_override.deleted',
            'description' => "Removed custom concurrency limits for {$tenant->name} (reverted to global)",
        ]);

        return back()->with('success', "Custom limits removed for {$tenant->name}. It will now inherit global settings.");
    }
}
