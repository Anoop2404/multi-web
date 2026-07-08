<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AgeCategory;
use App\Models\ClassCategory;
use App\Models\Designation;
use App\Models\Subject;
use App\Models\TeachingType;

class MastersApiController extends ApiController
{
    public function classes()
    {
        return $this->ok(ClassCategory::global()->where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'label', 'min_class', 'max_class']));
    }

    public function subjects()
    {
        return $this->ok(Subject::where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'label']));
    }

    public function designations()
    {
        return $this->ok(Designation::where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'label']));
    }

    public function ageCategories()
    {
        return $this->ok(AgeCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'label', 'max_age']));
    }

    public function teachingTypes()
    {
        return $this->ok(TeachingType::global()->active()->orderBy('sort_order')->get(['id', 'code', 'label', 'min_class', 'max_class']));
    }
}
