<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\Achievement;
use App\Services\Audit\DataChangeLogger;
use App\Support\AchievementCatalog;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AchievementController extends SchoolAdminController
{
    public function index(Request $request)
    {
        $category = $request->string('category')->toString();
        $level = $request->string('level')->toString();
        $year = $request->string('academic_year')->toString();

        $achievements = Achievement::where('tenant_id', $this->school->id)
            ->when($category !== '', fn ($q) => $q->byCategory($category))
            ->when($level !== '', fn ($q) => $q->byLevel($level))
            ->when($year !== '', fn ($q) => $q->byAcademicYear($year))
            ->orderBy('display_order')
            ->orderByDesc('achieved_at')
            ->get();

        $years = Achievement::where('tenant_id', $this->school->id)
            ->whereNotNull('academic_year')
            ->distinct()
            ->orderByDesc('academic_year')
            ->pluck('academic_year');

        return $this->inertia('School/Achievements/Index', [
            'achievements' => $achievements,
            'categories' => AchievementCatalog::CATEGORIES,
            'levels' => AchievementCatalog::LEVELS,
            'academicYears' => $years,
            'filters' => [
                'category' => $category,
                'level' => $level,
                'academic_year' => $year,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['tenant_id'] = $this->school->id;
        $data['is_system_generated'] = false;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store(
                'achievements/'.$this->school->id,
                TenantStorage::uploadDisk()
            );
        }

        $achievement = Achievement::create($data);

        app(DataChangeLogger::class)->created(
            $achievement,
            'Achievement added',
            $this->school->id,
            'achievement',
            ['title' => $achievement->title],
        );

        return back()->with('success', 'Achievement added.');
    }

    public function update(Request $request, string $tenantId, Achievement $achievement)
    {
        abort_if($achievement->tenant_id !== $this->school->id, 403);
        abort_if($achievement->is_system_generated, 422, 'System-generated awards cannot be edited here.');

        $before = $achievement->only(['title', 'description', 'category', 'level', 'academic_year', 'achieved_at']);
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store(
                'achievements/'.$this->school->id,
                TenantStorage::uploadDisk()
            );
        }

        $achievement->update($data);

        app(DataChangeLogger::class)->updated(
            $achievement,
            'Achievement updated',
            DataChangeLogger::diff($before, $achievement->only(array_keys($before))),
            $this->school->id,
            'achievement',
        );

        return back()->with('success', 'Achievement updated.');
    }

    public function destroy(string $tenantId, Achievement $achievement)
    {
        abort_if($achievement->tenant_id !== $this->school->id, 403);
        abort_if($achievement->is_system_generated, 422, 'System-generated awards cannot be deleted here.');

        app(DataChangeLogger::class)->deleted(
            $achievement,
            'Achievement removed',
            $this->school->id,
            'achievement',
            $achievement->only(['title', 'category', 'level', 'academic_year']),
        );

        $achievement->delete();

        return back()->with('success', 'Achievement removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => ['nullable', 'string', Rule::in(AchievementCatalog::categoryKeys())],
            'level' => ['nullable', 'string', Rule::in(AchievementCatalog::levelKeys())],
            'academic_year' => 'nullable|string|max:20',
            'achieved_at' => 'nullable|date',
            'image' => 'nullable|image|max:4096',
        ]);

        $data['category'] = AchievementCatalog::normalizeCategory($data['category'] ?? null);
        $data['level'] = AchievementCatalog::normalizeLevel($data['level'] ?? null);

        return $data;
    }
}
