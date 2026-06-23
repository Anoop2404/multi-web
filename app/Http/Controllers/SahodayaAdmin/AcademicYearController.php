<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AcademicYearRecord;
use App\Models\FinancialYear;
use App\Models\SahodayaProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicYearController extends SahodayaAdminController
{
    public function index()
    {
        $academicYears = AcademicYearRecord::orderByDesc('start_date')->get();
        $financialYears = FinancialYear::orderByDesc('start_date')->get();

        $currentAy = $academicYears->firstWhere('status', 'active');
        $currentFy = $financialYears->firstWhere('is_current', true);

        // Suggestions for next year to create
        $latestLabel = $academicYears->first()?->label;
        $suggestedYears = $this->suggestYears($latestLabel);

        return $this->inertia('Sahodaya/AcademicYears/Index', [
            'academicYears'  => $academicYears,
            'financialYears' => $financialYears,
            'currentAy'      => $currentAy,
            'currentFy'      => $currentFy,
            'suggestedYears' => $suggestedYears,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label'      => ['required', 'string', 'max:10', 'regex:/^\d{4}-\d{2}$/',
                             Rule::unique('academic_years', 'label')],
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
        ]);

        AcademicYearRecord::create(array_merge($data, ['status' => 'upcoming']));

        return back()->with('success', "Academic year {$data['label']} created.");
    }

    public function activate(Request $request, string $tenantId, AcademicYearRecord $academicYear)
    {
        if ($academicYear->status === 'closed') {
            return back()->with('error', 'A closed academic year cannot be re-activated.');
        }

        // Close any currently active year
        AcademicYearRecord::where('status', 'active')->update([
            'status'    => 'closed',
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
        ]);

        $academicYear->update([
            'status'    => 'active',
            'opened_by' => $request->user()->id,
            'opened_at' => now(),
        ]);

        // Keep sahodaya_profiles.active_academic_year in sync so existing string-based code still works
        SahodayaProfile::where('tenant_id', $this->sahodaya->id)
            ->update(['active_academic_year' => $academicYear->label]);

        // Link fee slabs and registration windows that use this label
        \App\Models\MembershipFeeSlab::where('sahodaya_id', $this->sahodaya->id)
            ->where('academic_year', $academicYear->label)
            ->whereNull('academic_year_id')
            ->update(['academic_year_id' => $academicYear->id]);

        \App\Models\SahodayaRegistrationWindow::where('sahodaya_id', $this->sahodaya->id)
            ->where('academic_year', $academicYear->label)
            ->whereNull('academic_year_id')
            ->update(['academic_year_id' => $academicYear->id]);

        return back()->with('success', "Academic year {$academicYear->label} is now active.");
    }

    public function close(Request $request, string $tenantId, AcademicYearRecord $academicYear)
    {
        if ($academicYear->status !== 'active') {
            return back()->with('error', 'Only the active academic year can be closed.');
        }

        $academicYear->update([
            'status'    => 'closed',
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
        ]);

        return back()->with('success', "Academic year {$academicYear->label} closed.");
    }

    public function storeFinancialYear(Request $request)
    {
        $data = $request->validate([
            'label'      => ['required', 'string', 'max:10', 'regex:/^\d{4}-\d{2}$/',
                             Rule::unique('financial_years', 'label')],
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
        ]);

        if ($request->boolean('set_current')) {
            FinancialYear::where('is_current', true)->update(['is_current' => false]);
            $data['is_current'] = true;
        }

        FinancialYear::create($data);

        return back()->with('success', "Financial year {$data['label']} created.");
    }

    public function setCurrentFinancialYear(string $tenantId, FinancialYear $financialYear)
    {
        FinancialYear::where('is_current', true)->update(['is_current' => false]);
        $financialYear->update(['is_current' => true]);

        return back()->with('success', "Financial year {$financialYear->label} set as current.");
    }

    /** Generate 3 year label suggestions beyond the latest existing year */
    private function suggestYears(?string $latestLabel): array
    {
        if ($latestLabel && preg_match('/^(\d{4})-\d{2}$/', $latestLabel, $m)) {
            $startYear = (int) $m[1];
        } else {
            $startYear = (int) date('Y');
        }

        $suggestions = [];
        for ($i = 1; $i <= 3; $i++) {
            $y = $startYear + $i;
            $suggestions[] = [
                'label'      => AcademicYearRecord::labelFromYear($y),
                'start_date' => AcademicYearRecord::defaultStartDate($y),
                'end_date'   => AcademicYearRecord::defaultEndDate($y),
            ];
        }

        return $suggestions;
    }
}
