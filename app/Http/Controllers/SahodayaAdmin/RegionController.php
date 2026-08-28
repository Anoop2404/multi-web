<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\CsvSafety;
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
            ->globalOnly()
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
            'code'        => Region::generateUniqueCode($this->sahodaya->id, ($data['code'] ?? null) ?: $data['name']),
            'description' => $data['description'] ?? null,
            'is_active'   => $data['is_active'] ?? true,
            'sort_order'  => (int) (Region::forTenant($this->sahodaya->id)->globalOnly()->max('sort_order') ?? 0) + 1,
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
            'code'        => Region::generateUniqueCode($this->sahodaya->id, ($data['code'] ?? null) ?: $data['name'], null, $region->id),
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

        // Check for event partitions keyed to this region before allowing deletion.
        $partitionKey = \Illuminate\Support\Str::slug($region->code ?: $region->name);
        $partitionEvents = \App\Models\FestEvent::query()
            ->where('tenant_id', $this->sahodaya->id)
            ->where('partition_key', $partitionKey)
            ->whereNotNull('parent_event_id')
            ->exists();

        $assignedSchools = \App\Models\FestEventSchoolPartition::query()
            ->where('tenant_id', $this->sahodaya->id)
            ->where('partition_key', $partitionKey)
            ->distinct()
            ->pluck('event_id')
            ->all();

        if ($partitionEvents || $assignedSchools !== []) {
            $details = [];
            if ($partitionEvents) {
                $details[] = 'active event partitions';
            }
            if ($assignedSchools !== []) {
                $details[] = count($assignedSchools).' school assignments across events';
            }
            abort(422, 'Cannot delete "'.$region->name.'" — it still has '.implode(' and ', $details).'. Reassign schools to another region or sync partitions first.');
        }

        $name = $region->name;
        $region->delete();

        $audit->log('region.deleted', "Region deleted: {$name}", properties: [
            'tenant_id' => $this->sahodaya->id,
        ]);

        return back()->with('success', 'Region removed. Schools in it are now unassigned.');
    }

    public function assign(Request $request, PlatformAuditLogger $audit)
    {
        $regionIds = Region::forTenant($this->sahodaya->id)->globalOnly()->pluck('id')->all();

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
            
            // Auto-sync the school into its region's partition on active events
            app(\App\Services\Events\FestRegionPartitionService::class)
                ->syncSchoolAcrossHubs($this->sahodaya->id, $row['school_id']);
        }

        $audit->log('region.schools_assigned', "Assigned {$saved} school(s) to regions", properties: [
            'tenant_id'     => $this->sahodaya->id,
            'academic_year' => $year,
        ]);

        return back()->with('success', "{$saved} school region assignment(s) saved.");
    }

    /**
     * Sahodaya-level region assignment report — NOT tied to any FestEvent. Requested
     * directly: "a report which is not related to events, a report for sahodaya which
     * has regions, to get region assigned schools and schools which don't have regions."
     * RegionController::index() already lists every school with its region_id (or null)
     * for the assignment UI; this reshapes the same underlying data (active-year
     * SchoolRegionAssignment) into a report: schools grouped by region, plus a distinct
     * "no region assigned" bucket, with counts — the assignment UI doesn't group or
     * count, and isn't meant to be exported/printed the way a report is.
     */
    public function report()
    {
        $year = AcademicYear::forSahodaya($this->sahodaya->id);

        [$regions, $unassigned, $totals] = $this->regionReportData($year);

        return $this->inertia('Sahodaya/Regions/Report', [
            'regions'      => $regions,
            'unassigned'   => $unassigned,
            'totals'       => $totals,
            'academicYear' => $year,
        ]);
    }

    public function exportReport()
    {
        $year = AcademicYear::forSahodaya($this->sahodaya->id);
        [$regions, $unassigned] = $this->regionReportData($year);

        $sahodayaSlug = Str::slug($this->sahodaya->name);
        $filename = "{$sahodayaSlug}-region-assignment-{$year}.csv";

        return response()->streamDownload(function () use ($regions, $unassigned) {
            $out = fopen('php://output', 'w');
            CsvSafety::fputcsv($out, ['Region', 'Region code', 'School', 'School prefix']);

            foreach ($regions as $region) {
                foreach ($region['schools'] as $school) {
                    CsvSafety::fputcsv($out, [$region['name'], $region['code'], $school['name'], $school['school_prefix']]);
                }
            }
            foreach ($unassigned as $school) {
                CsvSafety::fputcsv($out, ['— No region assigned —', '', $school['name'], $school['school_prefix']]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: array<string, int>}
     */
    private function regionReportData(string $year): array
    {
        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name', 'school_prefix']);

        $assignments = SchoolRegionAssignment::forTenant($this->sahodaya->id)
            ->forYear($year)
            ->pluck('region_id', 'school_id');

        $allRegions = Region::forTenant($this->sahodaya->id)
            ->globalOnly()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $schoolsByRegion = [];
        $unassigned = [];

        foreach ($schools as $school) {
            $regionId = $assignments[$school->id] ?? null;
            $row = ['id' => $school->id, 'name' => $school->name, 'school_prefix' => $school->school_prefix];

            if ($regionId === null) {
                $unassigned[] = $row;
            } else {
                $schoolsByRegion[$regionId][] = $row;
            }
        }

        $regions = $allRegions->map(fn (Region $region) => [
            'id'      => $region->id,
            'name'    => $region->name,
            'code'    => $region->code,
            'is_active' => $region->is_active,
            'schools' => $schoolsByRegion[$region->id] ?? [],
            'count'   => count($schoolsByRegion[$region->id] ?? []),
        ])->values()->all();

        $totals = [
            'schools'          => $schools->count(),
            'regions'          => $allRegions->count(),
            'assigned'         => $schools->count() - count($unassigned),
            'unassigned'       => count($unassigned),
        ];

        return [$regions, $unassigned, $totals];
    }
}
