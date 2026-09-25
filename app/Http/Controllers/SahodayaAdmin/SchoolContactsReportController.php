<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\Tenant;
use App\Support\ExcelExport;
use App\Support\PdfGenerator;
use App\Support\TenantBranding;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sahodaya-wide (not tied to any one event) directory of every approved member school's
 * Principal, Vice Principal and Events Coordinator, as recorded on the school's own
 * membership application. Meant for event managers who need to reach school heads
 * regardless of which fest they are running.
 */
class SchoolContactsReportController extends SahodayaAdminController
{
    /** Selectable contact roles: key => [label, payload-derived row prefix]. */
    private const ROLES = [
        'principal'   => ['label' => 'Principal',       'prefix' => 'principal'],
        'vice'        => ['label' => 'Vice Principal',  'prefix' => 'vice_principal'],
        'coordinator' => ['label' => 'Event Manager',   'prefix' => 'coordinator'],
    ];

    public function index()
    {
        $rows = $this->rows();

        return $this->inertia('Sahodaya/Schools/ContactsReport', [
            'rows'  => $rows,
            'stats' => [
                'total'                => count($rows),
                'missing_principal'    => collect($rows)->filter(fn ($r) => blank($r['principal_name']) && blank($r['principal_phone']))->count(),
                'missing_vice'         => collect($rows)->filter(fn ($r) => blank($r['vice_principal_name']) && blank($r['vice_principal_phone']))->count(),
            ],
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $roles = $this->selectedRoles($request);

        $headers = ['Sl No', 'School', 'Code'];
        foreach ($roles as $key) {
            $label = self::ROLES[$key]['label'];
            array_push($headers, $label, "{$label} Phone", "{$label} Email");
        }
        array_push($headers, 'School Phone', 'School Email');

        $rows = collect($this->rows())->map(function (array $r) use ($roles) {
            $line = [$r['sl_no'], $r['school'], $r['code']];
            foreach ($roles as $key) {
                $p = self::ROLES[$key]['prefix'];
                array_push($line, $r["{$p}_name"], $r["{$p}_phone"], $r["{$p}_email"]);
            }

            return array_merge($line, [$r['school_phone'], $r['school_email']]);
        });

        return ExcelExport::download('school-principals-contacts', $headers, $rows);
    }

    /** Pass ?preview=1 to open inline in the browser instead of downloading. */
    public function exportPdf(Request $request)
    {
        $html = view('sahodaya.reports.school-contacts', [
            'sahodaya'    => $this->sahodaya,
            'rows'        => $this->rows(),
            'roles'       => collect($this->selectedRoles($request))->mapWithKeys(fn ($k) => [$k => self::ROLES[$k]]),
            'generatedAt' => now()->format('d M Y, h:i A'),
            'logoSrc'     => TenantBranding::logoEmbedSrc($this->sahodaya),
        ])->render();

        return PdfGenerator::download($html, 'school-principals-contacts.pdf', $request->boolean('preview'), true);
    }

    /** @return list<string> role keys in canonical order; defaults to all when none/invalid are sent */
    private function selectedRoles(Request $request): array
    {
        $picked = array_values(array_intersect(array_keys(self::ROLES), (array) $request->input('include', [])));

        return $picked === [] ? array_keys(self::ROLES) : $picked;
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        $prefix = $this->sahodaya->sahodayaProfile?->prefix ?? 'SCH';

        return Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('name')
            ->get()
            ->values()
            ->map(function (Tenant $school, int $i) use ($prefix) {
                $p = $school->application_payload ?? [];

                return [
                    'id'                   => $school->id,
                    'sl_no'                => $i + 1,
                    'school'               => $school->name,
                    'code'                 => $school->school_no ? "{$prefix}-".str_pad((string) $school->school_no, 3, '0', STR_PAD_LEFT) : '',
                    'principal_name'       => $p['principal_name'] ?? '',
                    'principal_phone'      => $p['principal_phone'] ?? '',
                    'principal_email'      => $p['principal_email'] ?? '',
                    'vice_principal_name'  => $p['vice_principal_name'] ?? '',
                    'vice_principal_phone' => $p['vice_principal_phone'] ?? '',
                    'vice_principal_email' => $p['vice_principal_email'] ?? '',
                    'coordinator_name'     => $p['event_coordinator_name'] ?? '',
                    'coordinator_phone'    => $p['event_coordinator_phone'] ?? '',
                    'coordinator_email'    => $p['event_coordinator_email'] ?? '',
                    'school_phone'         => $p['phone'] ?? $p['contact_phone'] ?? '',
                    'school_email'         => $p['school_email'] ?? $p['contact_email'] ?? '',
                ];
            })
            ->all();
    }
}
