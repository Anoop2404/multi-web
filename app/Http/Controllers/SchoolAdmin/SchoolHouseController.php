<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Support\PersistDefaults;
use App\Models\SchoolHouse;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolHouseController extends SchoolAdminController
{
    public function index()
    {
        $houses = SchoolHouse::forSchool($this->school->id)
            ->withCount('students')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $unassigned = Student::where('tenant_id', $this->school->id)
            ->active()
            ->whereNull('school_house_id')
            ->count();

        $ranking = app(\App\Services\Events\SchoolHouseFestPointsService::class)
            ->rankingForSchool($this->school->id);

        return $this->inertia('School/Houses/Index', [
            'houses'     => $houses,
            'unassigned' => $unassigned,
            'ranking'    => $ranking,
            'students'   => Student::where('tenant_id', $this->school->id)
                ->active()
                ->with('schoolClass')
                ->orderBy('name')
                ->get(['id', 'name', 'reg_no', 'school_class_id', 'school_house_id', 'gender']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'color'      => 'nullable|string|max:20',
            'motto'      => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data = PersistDefaults::coalesce($data, ['sort_order' => 0]);

        SchoolHouse::create(array_merge($data, ['tenant_id' => $this->school->id]));

        return back()->with('success', 'House created.');
    }

    public function update(Request $request, string $tenantId, SchoolHouse $house)
    {
        abort_if($house->tenant_id !== $this->school->id, 403);

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'color'      => 'nullable|string|max:20',
            'motto'      => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data = PersistDefaults::coalesce($data, ['sort_order' => $house->sort_order ?? 0]);

        $house->update($data);

        return back()->with('success', 'House updated.');
    }

    public function destroy(string $tenantId, SchoolHouse $house)
    {
        abort_if($house->tenant_id !== $this->school->id, 403);

        Student::where('school_house_id', $house->id)->update(['school_house_id' => null]);
        $house->delete();

        return back()->with('success', 'House removed.');
    }

    public function assignStudents(Request $request)
    {
        $data = $request->validate([
            'school_house_id' => [
                'nullable',
                Rule::exists(SchoolHouse::class, 'id')->where('tenant_id', $this->school->id),
            ],
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => [
                'integer',
                Rule::exists(Student::class, 'id')->where('tenant_id', $this->school->id),
            ],
        ]);

        Student::where('tenant_id', $this->school->id)
            ->whereIn('id', $data['student_ids'])
            ->update(['school_house_id' => $data['school_house_id']]);

        return back()->with('success', 'Students assigned to house.');
    }
}
