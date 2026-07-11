<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\TrainingCategory;
use Illuminate\Http\Request;

class TrainingCategoryController extends SahodayaAdminController
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => 'required|string|max:120',
            'code' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $maxOrder = (int) (TrainingCategory::forTenant($this->sahodaya->id)->max('display_order') ?? 0);

        TrainingCategory::create([
            'tenant_id' => $this->sahodaya->id,
            'label' => $data['label'],
            'code' => TrainingCategory::makeUniqueCode(
                $this->sahodaya->id,
                $data['label'],
                $data['code'] ?? null,
            ),
            'is_active' => $data['is_active'] ?? true,
            'display_order' => $data['display_order'] ?? ($maxOrder + 1),
        ]);

        return back()->with('success', 'Training category created.');
    }

    public function update(Request $request, string $tenantId, TrainingCategory $category)
    {
        abort_if($category->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'label' => 'required|string|max:120',
            'code' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $category->update([
            'label' => $data['label'],
            'code' => TrainingCategory::makeUniqueCode(
                $this->sahodaya->id,
                $data['label'],
                $data['code'] ?? $category->code,
                $category->id,
            ),
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : $category->is_active,
            'display_order' => $data['display_order'] ?? $category->display_order,
        ]);

        return back()->with('success', 'Training category updated.');
    }

    public function destroy(string $tenantId, TrainingCategory $category)
    {
        abort_if($category->tenant_id !== $this->sahodaya->id, 403);

        if ($category->programs()->exists()) {
            $category->update(['is_active' => false]);

            return back()->with('success', 'Category deactivated (still used by programs).');
        }

        $category->delete();

        return back()->with('success', 'Training category removed.');
    }
}
