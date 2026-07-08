<?php

namespace App\Services\Membership;

use App\Models\ClassCategory;
use App\Models\MasterClass;

class MasterClassService
{
    /** @var array<string, list<int|string>> */
    private const TEMPLATE_CLASSES = [
        'PRE'   => ['Nursery', 'LKG', 'UKG'],
        'PRY'   => [1, 2, 3, 4, 5],
        'UP'    => [6, 7, 8],
        'SEC'   => [9, 10],
        'SrSEC' => [11, 12],
    ];

    public function __construct(private EffectiveMasterDataResolver $resolver) {}

    /** Copy default templates (or generate from categories) when a Sahodaya has no class master yet. */
    public function ensureForSahodaya(string $sahodayaId): void
    {
        $this->ensureGlobalTemplates();

        if (! MasterClass::forSahodaya($sahodayaId)->exists()) {
            $this->cloneTemplates($sahodayaId);

            return;
        }

        $this->syncMissingTemplates($sahodayaId);
    }

    /** Ensure platform default class templates exist (LKG, UKG, 1–12). */
    public function ensureGlobalTemplates(): void
    {
        $categories = ClassCategory::global()->pluck('id', 'code');
        if ($categories->isEmpty()) {
            return;
        }

        $order = (int) MasterClass::whereNull('sahodaya_id')->max('display_order');

        foreach (self::TEMPLATE_CLASSES as $code => $names) {
            $categoryId = $categories[$code] ?? null;
            if (! $categoryId) {
                continue;
            }

            foreach ($names as $name) {
                MasterClass::firstOrCreate(
                    ['sahodaya_id' => null, 'name' => (string) $name],
                    [
                        'class_category_id' => $categoryId,
                        'display_order'     => ++$order,
                        'is_active'         => true,
                    ],
                );
            }
        }
    }

    private function cloneTemplates(string $sahodayaId): void
    {
        $templates = MasterClass::whereNull('sahodaya_id')->active()->orderBy('display_order')->get();

        if ($templates->isNotEmpty()) {
            foreach ($templates as $template) {
                MasterClass::create([
                    'sahodaya_id'       => $sahodayaId,
                    'class_category_id' => $template->class_category_id,
                    'name'              => $template->name,
                    'display_order'     => $template->display_order,
                    'is_active'         => true,
                ]);
            }

            return;
        }

        $this->seedFromCategoryRanges($sahodayaId);
    }

    private function syncMissingTemplates(string $sahodayaId): void
    {
        $templates = MasterClass::whereNull('sahodaya_id')->active()->orderBy('display_order')->get();

        foreach ($templates as $template) {
            MasterClass::firstOrCreate(
                [
                    'sahodaya_id' => $sahodayaId,
                    'name'        => $template->name,
                ],
                [
                    'class_category_id' => $template->class_category_id,
                    'display_order'     => $template->display_order,
                    'is_active'         => true,
                ],
            );
        }
    }

    private function seedFromCategoryRanges(string $sahodayaId): void
    {
        $order = 0;

        foreach ($this->resolver->classCategories($sahodayaId) as $category) {
            foreach ($this->namesForCategory($category) as $name) {
                MasterClass::create([
                    'sahodaya_id'       => $sahodayaId,
                    'class_category_id' => $category->id,
                    'name'              => $name,
                    'display_order'     => ++$order,
                    'is_active'         => true,
                ]);
            }
        }
    }

    /** @return list<string> */
    private function namesForCategory(ClassCategory $category): array
    {
        $templateNames = self::TEMPLATE_CLASSES[$category->code] ?? null;
        if ($templateNames !== null) {
            return array_map('strval', $templateNames);
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
