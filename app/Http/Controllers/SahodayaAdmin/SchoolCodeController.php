<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\Student;
use App\Models\Tenant;
use App\Support\ExcelExport;
use App\Support\PdfGenerator;
use App\Support\TenantBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolCodeController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $sahodayaPrefix = $this->sahodaya->sahodayaProfile?->prefix ?? 'SCH';

        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('name')
            ->get();

        $schoolIds = $schools->pluck('id')->all();

        // Count active students per school
        $studentCounts = Student::whereIn('tenant_id', $schoolIds)
            ->whereNull('deleted_at')
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, count(*) as count')
            ->pluck('count', 'tenant_id')
            ->all();

        // Sample student ID (e.g. STU/27/10495) per school
        $sampleStudents = Student::whereIn('tenant_id', $schoolIds)
            ->whereNotNull('reg_no')
            ->where('reg_no', '!=', '')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->groupBy('tenant_id')
            ->map(fn ($group) => $group->first()?->reg_no)
            ->all();

        $schoolRows = $schools->map(function (Tenant $school) use ($studentCounts, $sampleStudents, $sahodayaPrefix) {
            $payload = $school->application_payload ?? [];
            $affiliation = $payload['cbse_affiliation'] ?? $payload['affiliation_number'] ?? null;
            $email = $payload['school_email'] ?? $payload['contact_email'] ?? null;
            $phone = $payload['phone'] ?? $payload['contact_phone'] ?? null;

            return [
                'id'                => $school->id,
                'name'              => $school->name,
                'school_no'         => $school->school_no,
                'school_prefix'     => $school->school_prefix,
                'school_code'       => $school->school_no ? "{$sahodayaPrefix}-".str_pad((string) $school->school_no, 3, '0', STR_PAD_LEFT) : null,
                'affiliation'       => $affiliation,
                'email'             => $email,
                'phone'             => $phone,
                'students_count'    => (int) ($studentCounts[$school->id] ?? 0),
                'sample_student_id' => $sampleStudents[$school->id] ?? null,
                'created_at'        => $school->created_at?->format('Y-m-d'),
            ];
        })->values()->all();

        $totalSchools = count($schoolRows);
        $assignedCount = collect($schoolRows)->filter(fn ($s) => $s['school_no'] !== null)->count();
        $unassignedCount = $totalSchools - $assignedCount;
        $totalStudents = array_sum($studentCounts);

        return $this->inertia('Sahodaya/Schools/SchoolCodes', [
            'schools'         => $schoolRows,
            'sahodayaPrefix'  => $sahodayaPrefix,
            'stats'           => [
                'total_schools'    => $totalSchools,
                'assigned_count'   => $assignedCount,
                'unassigned_count' => $unassignedCount,
                'total_students'   => $totalStudents,
            ],
        ]);
    }

    public function autoAssign(Request $request)
    {
        $validated = $request->validate([
            'start_no' => 'required|integer|min:1',
            'order_by' => 'required|in:name_asc,name_desc,affiliation_asc,created_asc',
            'scope'    => 'required|in:all,unassigned',
        ]);

        $startNo = (int) $validated['start_no'];
        $orderBy = $validated['order_by'];
        $scope   = $validated['scope'];

        DB::transaction(function () use ($startNo, $orderBy, $scope) {
            $query = Tenant::where('parent_id', $this->sahodaya->id)
                ->where('type', 'school')
                ->where('membership_status', 'approved')
                ->lockForUpdate();

            if ($scope === 'unassigned') {
                $query->whereNull('school_no');
            }

            match ($orderBy) {
                'name_desc'       => $query->orderByDesc('name'),
                'affiliation_asc' => $query->orderBy('name'),
                'created_asc'     => $query->orderBy('created_at'),
                default           => $query->orderBy('name'),
            };

            $schools = $query->get();

            // Find used school numbers if assigning only unassigned
            $usedNumbers = [];
            if ($scope === 'unassigned') {
                $usedNumbers = Tenant::where('parent_id', $this->sahodaya->id)
                    ->where('type', 'school')
                    ->whereNotNull('school_no')
                    ->pluck('school_no')
                    ->map(fn ($n) => (int) $n)
                    ->all();
            }

            // If scope is 'all', reset school_no first so unique index doesn't collide
            if ($scope === 'all') {
                Tenant::where('parent_id', $this->sahodaya->id)
                    ->where('type', 'school')
                    ->update(['school_no' => null]);
            }

            $currentNo = $startNo;
            foreach ($schools as $school) {
                if ($scope === 'unassigned') {
                    while (in_array($currentNo, $usedNumbers, true)) {
                        $currentNo++;
                    }
                }

                $school->update(['school_no' => $currentNo]);
                $usedNumbers[] = $currentNo;
                $currentNo++;
            }
        });

        return back()->with('success', 'School codes auto-assigned successfully.');
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'assignments'                 => 'required|array',
            'assignments.*.id'            => 'required|string',
            'assignments.*.school_no'     => 'nullable|integer|min:1',
            'assignments.*.school_prefix' => 'nullable|string|max:20',
        ]);

        $assignments = $validated['assignments'];

        // Validate uniqueness of non-null school_no values within the payload
        $numbers = array_filter(array_column($assignments, 'school_no'), fn ($n) => $n !== null && $n !== '');
        if (count($numbers) !== count(array_unique($numbers))) {
            return back()->withErrors(['assignments' => 'School number must be unique for each school.']);
        }

        DB::transaction(function () use ($assignments) {
            $schoolIds = array_column($assignments, 'id');

            // Temporarily clear to avoid collisions during reassignment
            Tenant::where('parent_id', $this->sahodaya->id)
                ->whereIn('id', $schoolIds)
                ->lockForUpdate()
                ->update(['school_no' => null]);

            foreach ($assignments as $row) {
                Tenant::where('id', $row['id'])
                    ->where('parent_id', $this->sahodaya->id)
                    ->update([
                        'school_no'     => ! empty($row['school_no']) ? (int) $row['school_no'] : null,
                        'school_prefix' => ! empty($row['school_prefix']) ? mb_strtoupper(trim($row['school_prefix'])) : null,
                    ]);
            }
        });

        return back()->with('success', 'School code assignments saved successfully.');
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $data = $this->schoolCodeExportRows();

        $headers = [
            'Sl No',
            'School Code',
            'School No.',
            'Short Prefix',
            'School Name',
            'CBSE Affiliation No.',
            'Total Students',
            'Sample Student ID',
            'Email',
            'Phone',
        ];

        return ExcelExport::download('school-code-directory', $headers, $data);
    }

    public function exportPdf(Request $request)
    {
        $sahodayaPrefix = $this->sahodaya->sahodayaProfile?->prefix ?? 'SCH';
        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('school_no')
            ->orderBy('name')
            ->get();

        $schoolIds = $schools->pluck('id')->all();
        $studentCounts = Student::whereIn('tenant_id', $schoolIds)
            ->whereNull('deleted_at')
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, count(*) as count')
            ->pluck('count', 'tenant_id')
            ->all();

        $rows = $schools->map(function (Tenant $s, $idx) use ($sahodayaPrefix, $studentCounts) {
            $payload = $s->application_payload ?? [];

            return [
                'sl_no'          => $idx + 1,
                'name'           => $s->name,
                'code'           => $s->school_no ? "{$sahodayaPrefix}-".str_pad((string) $s->school_no, 3, '0', STR_PAD_LEFT) : '—',
                'school_no'      => $s->school_no ?? '—',
                'prefix'         => $s->school_prefix ?? '—',
                'affiliation'    => $payload['cbse_affiliation'] ?? $payload['affiliation_number'] ?? '—',
                'students_count' => (int) ($studentCounts[$s->id] ?? 0),
                'email'          => $payload['school_email'] ?? $payload['contact_email'] ?? '—',
                'phone'          => $payload['phone'] ?? $payload['contact_phone'] ?? '—',
            ];
        });

        $html = view('sahodaya.reports.school-codes', [
            'sahodaya'       => $this->sahodaya,
            'sahodayaPrefix' => $sahodayaPrefix,
            'rows'           => $rows,
            'totalSchools'   => count($rows),
            'totalAssigned'  => $rows->filter(fn ($r) => $r['code'] !== '—')->count(),
            'totalStudents'  => array_sum($studentCounts),
            'generatedAt'    => now()->format('d M Y, h:i A'),
            'logoSrc'        => TenantBranding::logoEmbedSrc($this->sahodaya),
        ])->render();

        return PdfGenerator::download($html, 'school-code-directory.pdf');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $this->schoolCodeExportRows();

        $headers = [
            'Sl No',
            'School Code',
            'School No.',
            'Short Prefix',
            'School Name',
            'CBSE Affiliation No.',
            'Total Students',
            'Sample Student ID',
            'Email',
            'Phone',
        ];

        return response()->streamDownload(function () use ($headers, $data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($data as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'school-code-directory.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /** @return list<list<mixed>> */
    private function schoolCodeExportRows(): array
    {
        $sahodayaPrefix = $this->sahodaya->sahodayaProfile?->prefix ?? 'SCH';
        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('school_no')
            ->orderBy('name')
            ->get();

        $schoolIds = $schools->pluck('id')->all();
        $studentCounts = Student::whereIn('tenant_id', $schoolIds)
            ->whereNull('deleted_at')
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, count(*) as count')
            ->pluck('count', 'tenant_id')
            ->all();

        $sampleStudents = Student::whereIn('tenant_id', $schoolIds)
            ->whereNotNull('reg_no')
            ->where('reg_no', '!=', '')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->groupBy('tenant_id')
            ->map(fn ($group) => $group->first()?->reg_no)
            ->all();

        $rows = [];
        foreach ($schools as $idx => $s) {
            $payload = $s->application_payload ?? [];
            $code = $s->school_no ? "{$sahodayaPrefix}-".str_pad((string) $s->school_no, 3, '0', STR_PAD_LEFT) : '—';

            $rows[] = [
                $idx + 1,
                $code,
                $s->school_no ?? '',
                $s->school_prefix ?? '',
                $s->name,
                $payload['cbse_affiliation'] ?? $payload['affiliation_number'] ?? '',
                (int) ($studentCounts[$s->id] ?? 0),
                $sampleStudents[$s->id] ?? '',
                $payload['school_email'] ?? $payload['contact_email'] ?? '',
                $payload['phone'] ?? $payload['contact_phone'] ?? '',
            ];
        }

        return $rows;
    }
}
