<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassCategory;
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
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $teachingType->update($data);

        return back()->with('success', 'Teaching type updated.');
    }
}
