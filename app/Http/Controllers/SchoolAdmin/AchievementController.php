<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\Achievement;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class AchievementController extends SchoolAdminController
{
    public function index()
    {
        $achievements = Achievement::where('tenant_id', $this->school->id)
            ->orderBy('display_order')->get();

        return $this->inertia('School/Achievements/Index', compact('achievements'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'nullable|string|max:100',
            'level'       => 'nullable|string|max:100',
            'achieved_at' => 'nullable|date',
            'image'       => 'nullable|image|max:4096',
        ]);

        $data['tenant_id'] = $this->school->id;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('achievements/' . $this->school->id, \App\Support\TenantStorage::uploadDisk());
        }

        Achievement::create($data);
        return back()->with('success', 'Achievement added.');
    }

    public function update(Request $request, string $tenantId, Achievement $achievement)
    {
        abort_if($achievement->tenant_id !== $this->school->id, 403);

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'nullable|string|max:100',
            'level'       => 'nullable|string|max:100',
            'achieved_at' => 'nullable|date',
            'image'       => 'nullable|image|max:4096',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('achievements/' . $this->school->id, \App\Support\TenantStorage::uploadDisk());
        }

        $achievement->update($data);
        return back()->with('success', 'Achievement updated.');
    }

    public function destroy(string $tenantId, Achievement $achievement)
    {
        abort_if($achievement->tenant_id !== $this->school->id, 403);
        $achievement->delete();
        return back()->with('success', 'Achievement removed.');
    }
}
