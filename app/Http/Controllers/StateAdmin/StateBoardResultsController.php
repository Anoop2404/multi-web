<?php

namespace App\Http\Controllers\StateAdmin;

use App\Http\Controllers\Controller;
use App\Models\BoardResult;
use App\Models\Tenant;
use App\Support\AcademicYear;
use App\Support\TenancyDatabase;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * State-level consolidated board-results dashboard (#149) — light MVP.
 */
class StateBoardResultsController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->string('academic_year')->toString() ?: AcademicYear::calendarCurrent();
        $sahodayas = Tenant::query()
            ->where('type', 'sahodaya')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $clusters = [];
        $totals = [
            'sahodayas' => 0,
            'published' => 0,
            'pending' => 0,
            'schools_reported' => 0,
            'avg_pass_percent' => null,
        ];

        $passSum = 0.0;
        $passN = 0;

        foreach ($sahodayas as $sahodaya) {
            $schoolIds = TenancyDatabase::schoolIdsFor($sahodaya->id);
            $stats = TenancyDatabase::whenDatabaseReady($sahodaya, function () use ($schoolIds, $year) {
                $results = BoardResult::query()
                    ->whereIn('tenant_id', $schoolIds)
                    ->where('academic_year', $year)
                    ->get(['id', 'tenant_id', 'status', 'pass_percent', 'class', 'examination_type']);

                $published = $results->where('status', BoardResult::STATUS_PUBLISHED);
                $pending = $results->whereIn('status', [
                    BoardResult::STATUS_SUBMITTED,
                    BoardResult::STATUS_VERIFIED,
                    BoardResult::STATUS_APPROVED,
                ]);

                return [
                    'published' => $published->count(),
                    'pending' => $pending->count(),
                    'schools_reported' => $results->pluck('tenant_id')->unique()->count(),
                    'avg_pass_percent' => $published->count()
                        ? round((float) $published->avg('pass_percent'), 2)
                        : null,
                    'by_class' => [
                        10 => $published->where('class', 10)->count(),
                        12 => $published->where('class', 12)->count(),
                    ],
                ];
            }, [
                'published' => 0,
                'pending' => 0,
                'schools_reported' => 0,
                'avg_pass_percent' => null,
                'by_class' => [10 => 0, 12 => 0],
                'unavailable' => true,
            ]);

            $clusters[] = [
                'sahodaya_id' => $sahodaya->id,
                'sahodaya_name' => $sahodaya->name,
                ...$stats,
            ];

            $totals['sahodayas']++;
            $totals['published'] += $stats['published'];
            $totals['pending'] += $stats['pending'];
            $totals['schools_reported'] += $stats['schools_reported'];
            if ($stats['avg_pass_percent'] !== null) {
                $passSum += (float) $stats['avg_pass_percent'];
                $passN++;
            }
        }

        $totals['avg_pass_percent'] = $passN > 0 ? round($passSum / $passN, 2) : null;

        return Inertia::render('StateAdmin/BoardResults/Index', [
            'filters' => ['academic_year' => $year],
            'totals' => $totals,
            'clusters' => $clusters,
        ]);
    }
}
