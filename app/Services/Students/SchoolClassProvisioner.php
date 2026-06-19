<?php

namespace App\Services\Students;

use App\Models\ClassCategory;
use App\Models\MasterClass;
use App\Models\SchoolClass;
use App\Models\Tenant;
use App\Services\Membership\EffectiveMasterDataResolver;

class SchoolClassProvisioner
{
    public function __construct(private EffectiveMasterDataResolver $resolver) {}

    /** Ensure school has all classes defined by the parent Sahodaya's class master. */
    public function ensureForSchool(Tenant $school): int
    {
        $sahodayaId = $school->parent_id;
        if (! $sahodayaId) {
            return 0;
        }

        $masterClasses = $this->resolver->masterClasses($sahodayaId);

        if ($masterClasses->isNotEmpty()) {
            return $this->provisionFromMasterClasses($school, $masterClasses);
        }

        return $this->provisionFromCategoryRanges($school, $sahodayaId);
    }

    /** @param  \Illuminate\Support\Collection<int, MasterClass>  $masterClasses */
    private function provisionFromMasterClasses(Tenant $school, $masterClasses): int
    {
        $created = 0;

        foreach ($masterClasses as $masterClass) {
            $class = SchoolClass::firstOrCreate(
                [
                    'tenant_id' => $school->id,
                    'name'      => $masterClass->name,
                ],
                [
                    'class_category_id' => $masterClass->class_category_id,
                    'display_order'     => $masterClass->display_order,
                    'is_active'         => true,
                ],
            );

            if ($class->wasRecentlyCreated) {
                $created++;
            } else {
                $class->update([
                    'class_category_id' => $masterClass->class_category_id,
                    'display_order'     => $masterClass->display_order,
                    'is_active'         => true,
                ]);
            }
        }

        return $created;
    }

    private function provisionFromCategoryRanges(Tenant $school, string $sahodayaId): int
    {
        $categories = $this->resolver->classCategories($sahodayaId);
        $created = 0;
        $order = (int) SchoolClass::where('tenant_id', $school->id)->max('display_order');

        foreach ($categories as $category) {
            foreach ($this->classNamesForCategory($category) as $name) {
                $class = SchoolClass::firstOrCreate(
                    [
                        'tenant_id'         => $school->id,
                        'class_category_id' => $category->id,
                        'name'              => $name,
                    ],
                    [
                        'display_order' => ++$order,
                        'is_active'     => true,
                    ],
                );

                if ($class->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /** @return list<string> */
    private function classNamesForCategory(ClassCategory $category): array
    {
        if ($category->code === 'PRE') {
            return ['LKG', 'UKG'];
        }

        if ($category->min_class === null || $category->max_class === null) {
            return [];
        }

        $names = [];
        for ($i = $category->min_class; $i <= $category->max_class; $i++) {
            $names[] = (string) $i;
        }

        return $names;
    }
}
