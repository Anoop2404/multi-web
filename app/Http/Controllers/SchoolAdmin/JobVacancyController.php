<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\JobVacancy;
use Illuminate\Http\Request;

class JobVacancyController extends SchoolAdminController
{
    public function index(Request $request)
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();

        $vacancies = JobVacancy::where('tenant_id', $this->school->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhere('qualification', 'like', "%{$search}%")
                        ->orWhere('experience', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'hidden', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return $this->inertia('School/JobVacancies/Index', [
            'vacancies' => $vacancies,
            'filters' => compact('search', 'status'),
        ]);
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
