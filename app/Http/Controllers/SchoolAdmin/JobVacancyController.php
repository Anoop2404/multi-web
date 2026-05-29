<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\JobVacancy;
use Illuminate\Http\Request;

class JobVacancyController extends SchoolAdminController
{
    public function index()
    {
        $vacancies = JobVacancy::where('tenant_id', $this->school->id)
            ->orderByDesc('created_at')->get();

        return $this->inertia('School/JobVacancies/Index', compact('vacancies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'qualification' => 'nullable|string|max:500',
            'experience'    => 'nullable|string|max:255',
            'last_date'     => 'nullable|date',
            'apply_email'   => 'nullable|email|max:255',
            'is_active'     => 'boolean',
        ]);

        $data['tenant_id'] = $this->school->id;
        JobVacancy::create($data);

        return back()->with('success', 'Vacancy posted.');
    }

    public function update(Request $request, string $tenantId, JobVacancy $vacancy)
    {
        abort_if($vacancy->tenant_id !== $this->school->id, 403);

        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'qualification' => 'nullable|string|max:500',
            'experience'    => 'nullable|string|max:255',
            'last_date'     => 'nullable|date',
            'apply_email'   => 'nullable|email|max:255',
            'is_active'     => 'boolean',
        ]);

        $vacancy->update($data);
        return back()->with('success', 'Vacancy updated.');
    }

    public function destroy(string $tenantId, JobVacancy $vacancy)
    {
        abort_if($vacancy->tenant_id !== $this->school->id, 403);
        $vacancy->delete();
        return back()->with('success', 'Vacancy removed.');
    }
}
