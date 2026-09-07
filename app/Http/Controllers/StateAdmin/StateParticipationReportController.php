<?php

namespace App\Http\Controllers\StateAdmin;

use App\Http\Controllers\Controller;
use App\Models\FestStateProgram;
use App\Services\State\StateParticipationLimitService;
use App\Support\StateScope;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class StateParticipationReportController extends Controller
{
    public function index(FestStateProgram $stateProgram, StateParticipationLimitService $service)
    {
        StateScope::assertOwns($stateProgram->state_id);

        return Inertia::render('StateAdmin/Reports/ParticipationLimits', [
            'stateProgram' => $stateProgram->only(['id', 'title']),
            'sahodayaRows' => $service->sahodayaComplianceRows($stateProgram),
            'itemRows'     => $service->itemUtilizationRows($stateProgram),
            'exportUrl'    => route('admin.state.reports.participation.export', $stateProgram, false),
        ]);
    }

    public function export(FestStateProgram $stateProgram, StateParticipationLimitService $service)
    {
        StateScope::assertOwns($stateProgram->state_id);

        $rows = $service->sahodayaComplianceRows($stateProgram);

        return Response::streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Sahodaya', 'Item', 'Item Code', 'Approved Count', 'Max Per Sahodaya', 'Exceeds Limit']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['sahodaya_name'],
                    $row['item_title'],
                    $row['item_code'],
                    $row['approved_count'],
                    $row['max_per_school'] ?? 'Unlimited',
                    $row['exceeds'] ? 'Yes' : 'No',
                ]);
            }
            fclose($out);
        }, "state-participation-compliance-{$stateProgram->id}.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }
}
