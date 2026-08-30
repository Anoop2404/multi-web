<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\BoardResult;
use App\Support\AcademicYear;
use App\Support\TenantStorage;

class SchoolResultsController extends Controller
{
    use RendersPublicPages;

    public function index()
    {
        $tenant = $this->resolveTenant();

        abort_if($tenant->type !== 'school', 404);

        $year = AcademicYear::forSchool($tenant);

        // Only Class 10 (AISSE) and Class 12 (AISSCE) are CBSE board examinations —
        // and only the current active academic year's results are shown publicly.
        $results = BoardResult::where('tenant_id', $tenant->id)
            ->published()
            ->whereIn('class', [10, 12])
            ->where('academic_year', $year)
            ->with(['toppers' => fn ($q) => $q->overallEntries()->with('examStream')->orderBy('rank')])
            ->orderBy('class')
            ->get();

        return $this->renderPublic('public.results.index', $tenant, [
            'results' => $results,
            'year'    => $year,
            'pageSeo' => [
                'title'       => 'Board Results — '.$tenant->name,
                'description' => 'CBSE Class X and Class XII board examination results and toppers from '.$tenant->name,
                'og_type'     => 'website',
            ],
        ]);
    }

    public function downloadPdf(BoardResult $boardResult)
    {
        $tenant = $this->resolveTenant();

        abort_if($tenant->type !== 'school', 404);
        abort_unless($boardResult->tenant_id === $tenant->id, 404);
        abort_unless($boardResult->status === BoardResult::STATUS_PUBLISHED, 404);
        abort_unless($boardResult->result_pdf_path, 404);

        return TenantStorage::downloadPrivate(
            $boardResult->result_pdf_path,
            $boardResult->result_pdf_disk,
            "Class {$boardResult->class} Result {$boardResult->academic_year}.pdf",
        );
    }
}
