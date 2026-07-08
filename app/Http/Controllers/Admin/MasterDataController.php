<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgeCategory;
use App\Models\ClassCategory;
use App\Models\Designation;
use App\Models\Subject;
use App\Models\TeachingType;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    public function classCategories()
    {
        $categories = ClassCategory::global()->orderBy('sort_order')->get();

        return inertia('MasterData/ClassCategories', compact('categories'));
    }

    public function storeClassCategory(Request $request)
    {
        $data = $request->validate([
            'code'       => 'required|string|max:20|unique:class_categories,code,NULL,id,sahodaya_id,NULL',
            'label'      => 'required|string|max:100',
            'min_class'  => 'nullable|integer|min:1|max:12',
            'max_class'  => 'nullable|integer|min:1|max:12',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        ClassCategory::create(array_merge($data, [
            'sahodaya_id' => null,
            'sort_order'  => $data['sort_order'] ?? 0,
        ]));

        return back()->with('success', 'Class category added.');
    }

    public function updateClassCategory(Request $request, ClassCategory $classCategory)
    {
        abort_if($classCategory->sahodaya_id !== null, 403);

        $data = $request->validate([
            'code'       => 'required|string|max:20',
            'label'      => 'required|string|max:100',
            'min_class'  => 'nullable|integer|min:1|max:12',
            'max_class'  => 'nullable|integer|min:1|max:12',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $classCategory->update($data);

        return back()->with('success', 'Class category updated.');
    }

    public function teachingTypes()
    {
        $types = TeachingType::global()->orderBy('sort_order')->get();

        return inertia('MasterData/TeachingTypes', compact('types'));
    }

    public function storeTeachingType(Request $request)
    {
        $data = $request->validate([
            'code'       => 'required|string|max:20|unique:teaching_types,code,NULL,id,sahodaya_id,NULL',
            'label'      => 'required|string|max:100',
            'min_class'  => 'nullable|integer|min:0|max:12',
            'max_class'  => 'nullable|integer|min:0|max:12',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        TeachingType::create(array_merge($data, [
            'sahodaya_id' => null,
            'sort_order'  => $data['sort_order'] ?? 0,
        ]));

        return back()->with('success', 'Teaching type added.');
    }

    public function updateTeachingType(Request $request, TeachingType $teachingType)
    {
        abort_if($teachingType->sahodaya_id !== null, 403);

        $data = $request->validate([
            'code'       => 'required|string|max:20',
            'label'      => 'required|string|max:100',
            'min_class'  => 'nullable|integer|min:0|max:12',
            'max_class'  => 'nullable|integer|min:0|max:12',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $teachingType->update($data);

        return back()->with('success', 'Teaching type updated.');
    }

    public function subjects()
    {
        $subjects = Subject::global()->orderBy('sort_order')->get();

        return inertia('MasterData/Subjects', compact('subjects'));
    }

    public function storeSubject(Request $request)
    {
        $data = $request->validate([
            'code'       => 'required|string|max:30|unique:subjects,code,NULL,id,sahodaya_id,NULL',
            'label'      => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        Subject::create(array_merge($data, [
            'sahodaya_id' => null,
            'sort_order'  => $data['sort_order'] ?? 0,
        ]));

        return back()->with('success', 'Subject added.');
    }

    public function updateSubject(Request $request, Subject $subject)
    {
        abort_if($subject->sahodaya_id !== null, 403);

        $data = $request->validate([
            'code'       => 'required|string|max:30',
            'label'      => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $subject->update($data);

        return back()->with('success', 'Subject updated.');
    }

    public function designations()
    {
        $designations = Designation::global()->orderBy('sort_order')->get();

        return inertia('MasterData/Designations', compact('designations'));
    }

    public function storeDesignation(Request $request)
    {
        $data = $request->validate([
            'code'       => 'required|string|max:30|unique:designations,code,NULL,id,sahodaya_id,NULL',
            'label'      => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        Designation::create(array_merge($data, [
            'sahodaya_id' => null,
            'sort_order'  => $data['sort_order'] ?? 0,
        ]));

        return back()->with('success', 'Designation added.');
    }

    public function updateDesignation(Request $request, Designation $designation)
    {
        abort_if($designation->sahodaya_id !== null, 403);

        $data = $request->validate([
            'code'       => 'required|string|max:30',
            'label'      => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $designation->update($data);

        return back()->with('success', 'Designation updated.');
    }

    public function ageCategories()
    {
        $categories = AgeCategory::global()->orderBy('sort_order')->get();

        return inertia('MasterData/AgeCategories', compact('categories'));
    }

    public function storeAgeCategory(Request $request)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:20|unique:age_categories,code,NULL,id,sahodaya_id,NULL',
            'label'       => 'required|string|max:100',
            'max_age'     => 'required|integer|min:1|max:25',
            'cutoff_date' => 'required|string|max:10',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        AgeCategory::create(array_merge($data, [
            'sahodaya_id' => null,
            'sort_order'  => $data['sort_order'] ?? 0,
        ]));

        return back()->with('success', 'Age category added.');
    }

    public function updateAgeCategory(Request $request, AgeCategory $ageCategory)
    {
        abort_if($ageCategory->sahodaya_id !== null, 403);

        $data = $request->validate([
            'code'        => 'required|string|max:20',
            'label'       => 'required|string|max:100',
            'max_age'     => 'required|integer|min:1|max:25',
            'cutoff_date' => 'required|string|max:10',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ]);

        $ageCategory->update($data);

        return back()->with('success', 'Age category updated.');
    }
}
