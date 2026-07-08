<?php

namespace App\Services\Membership;

use App\Models\ClassCategory;
use App\Models\ClassCategoryOverride;
use App\Models\MasterClass;
use App\Models\Designation;
use App\Models\Subject;
use App\Models\TeachingType;
use App\Models\TeachingTypeOverride;
use Illuminate\Support\Collection;

class EffectiveMasterDataResolver
{
    public function classCategories(string $sahodayaId): Collection
    {
        $overrides = ClassCategoryOverride::where('sahodaya_id', $sahodayaId)
            ->get()
            ->keyBy('class_category_id');

        $hiddenIds = $overrides
            ->filter(fn ($override) => $override->is_hidden)
            ->keys();

        return ClassCategory::active()
            ->where(function ($q) use ($sahodayaId) {
                $q->whereNull('sahodaya_id')->orWhere('sahodaya_id', $sahodayaId);
            })
            ->whereNotIn('id', $hiddenIds)
            ->get()
            ->map(function (ClassCategory $category) use ($overrides) {
                $override = $overrides->get($category->id);
                if ($override?->sort_order !== null) {
                    $category->setAttribute('sort_order', $override->sort_order);
                }

                return $category;
            })
            ->sortBy(fn (ClassCategory $category) => [$category->sort_order, $category->label])
            ->values();
    }

    public function masterClasses(string $sahodayaId): \Illuminate\Support\Collection
    {
        $visibleCategoryIds = $this->classCategories($sahodayaId)->pluck('id');

        return MasterClass::active()
            ->forSahodaya($sahodayaId)
            ->whereIn('class_category_id', $visibleCategoryIds)
            ->with('classCategory')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    public function teachingTypes(string $sahodayaId): Collection
    {
        $hiddenIds = TeachingTypeOverride::where('sahodaya_id', $sahodayaId)
            ->where('is_hidden', true)
            ->pluck('teaching_type_id');

        return TeachingType::active()
            ->where(function ($q) use ($sahodayaId) {
                $q->whereNull('sahodaya_id')->orWhere('sahodaya_id', $sahodayaId);
            })
            ->whereNotIn('id', $hiddenIds)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    public function subjects(string $sahodayaId): Collection
    {
        return Subject::active()->forSahodaya($sahodayaId)->orderBy('sort_order')->orderBy('label')->get();
    }

    public function designations(string $sahodayaId): Collection
    {
        return Designation::active()->forSahodaya($sahodayaId)->orderBy('sort_order')->orderBy('label')->get();
    }

    public function ageCategories(string $sahodayaId): Collection
    {
        return \App\Models\AgeCategory::active()->forSahodaya($sahodayaId)->orderBy('sort_order')->orderBy('label')->get();
    }
}
