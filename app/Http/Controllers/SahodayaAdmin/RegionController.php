<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\Region;
use App\Models\SchoolRegionAssignment;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Sahodaya-level Regions for Kalotsav. Regions sit between the Sahodaya and its
 * schools (State → Sahodaya → Region → School). Schools can be assigned to a
 * region here, and schools also pick their region during annual registration.
 */
class RegionController extends SahodayaAdminController
{
    public function index()
    {
        $year = AcademicYear::forSahodaya($this->sahodaya->id);

        $regions = Region::forTenant($this->sahodaya->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Region $region) => [
                'id'          => $region->id,
                'name'        => $region->name,
                'code'        => $region->code,
                'description' => $region->description,
                'sort_order'  => $region->sort_order,
                'is_active'   => $region->is_active,
            ]);

        $assignments = SchoolRegionAssignment::forTenant($this->sahodaya->id)
            ->forYear($year)
            ->pluck('region_id', 'school_id');

        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name', 'school_prefix'])
            ->map(fn (Tenant $school) => [
                'id'            => $school->id,
                'name'          => $school->name,
                'school_prefix' => $school->school_prefix,
                'region_id'     => $assignments[$school->id] ?? null,
            ]);

        return $this->inertia('Sahodaya/Regions/Index', [
            'regions'      => $regions,
            'schools'      => $schools,
            'academicYear' => $year,
        ]);
    }

    public function store(Request $request, PlatformAuditLogger $audit)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'code'        => 'nullable|string|max:64',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ]);

        $region = Region::create([
            'tenant_id'   => $this->sahodaya->id,
            'name'        => $data['name'],
            'code'        => $this->uniqueCode($data['code'] ?? null, $data['name']),
            'description' => $data['description'] ?? null,
            'is_active'   => $data['is_active'] ?? true,
            'sort_order'  => (int) (Region::forTenant($this->sahodaya->id)->max('sort_order') ?? 0) + 1,
        ]);

        $audit->log('region.created', "Region created: {$region->name}", $region, [
            'tenant_id' => $this->sahodaya->id,
        ]);

        return back()->with('success', "Region \"{$region->name}\" created.");
    }

    public function update(Request $request, string $tenantId, Region $region, PlatformAuditLogger $audit)
    {
        abort_if($region->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'code'        => 'nullable|string|max:64',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ]);

        $region->update([
            'name'        => $data['name'],
            'code'        => $this->uniqueCode($data['code'] ?? null, $data['name'], $region->id),
            'description' => $data['description'] ?? null,
            'is_active'   => $data['is_active'] ?? $region->is_active,
        ]);

        $audit->log('region.updated', "Region updated: {$region->name}", $region, [
            'tenant_id' => $this->sahodaya->id,
        ]);

        return back()->with('success', 'Region updated.');
    }

    public function destroy(string $tenantId, Region $region, PlatformAuditLogger $audit)
    {
        abort_if($region->tenant_id !== $this->sahodaya->id, 403);

        $name = $region->name;
        $region->delete();

        $audit->log('region.deleted', "Region deleted: {$name}", properties: [
            'tenant_id' => $this->sahodaya->id,
        ]);

        return back()->with('success', 'Region removed. Schools in it are now unassigned.');
    }

    public function assign(Request $request, PlatformAuditLogger $audit)
    {
        $regionIds = Region::forTenant($this->sahodaya->id)->pluck('id')->all();

        $data = $request->validate([
            'assignments'               => 'required|array',
            'assignments.*.school_id'   => ['required', 'string'],
            'assignments.*.region_id'   => ['nullable', Rule::in($regionIds)],
        ]);

        $year = AcademicYear::forSahodaya($this->sahodaya->id);
        $schoolIds = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->pluck('id')
            ->all();

        $saved = 0;
        foreach ($data['assignments'] as $row) {
            if (! in_array($row['school_id'], $schoolIds, true)) {
                continue;
            }

            if (empty($row['region_id'])) {
                SchoolRegionAssignment::forTenant($this->sahodaya->id)
                    ->forYear($year)
                    ->where('school_id', $row['school_id'])
                    ->delete();
                $saved++;

                continue;
            }

            SchoolRegionAssignment::updateOrCreate(
                ['school_id' => $row['school_id'], 'academic_year' => $year],
                [
                    'tenant_id'           => $this->sahodaya->id,
                    'region_id'           => $row['region_id'],
                    'source'              => 'sahodaya',
                    'assigned_by_user_id' => $request->user()?->id,
                ],
            );
            $saved++;
        }

        $audit->log('region.schools_assigned', "Assigned {$saved} school(s) to regions", properties: [
            'tenant_id'     => $this->sahodaya->id,
            'academic_year' => $year,
        ]);

        return back()->with('success', "{$saved} school region assignment(s) saved.");
    }

    private function uniqueCode(?string $code, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($code ?: $name) ?: 'region';
        $candidate = $base;
        $i = 1;

        while (Region::forTenant($this->sahodaya->id)
            ->where('code', $candidate)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $base.'-'.(++$i);
        }

        return $candidate;
    }
}
