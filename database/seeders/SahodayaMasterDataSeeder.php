<?php

namespace Database\Seeders;

use App\Models\ClassCategory;
use App\Models\TeachingType;
use App\Services\Membership\MasterClassService;
use Illuminate\Database\Seeder;

class SahodayaMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'PRE',   'label' => 'Pre-Primary',       'min_class' => null, 'max_class' => null, 'sort_order' => 0],
            ['code' => 'PRY',   'label' => 'Primary',           'min_class' => 1,  'max_class' => 5,  'sort_order' => 1],
            ['code' => 'UP',    'label' => 'Middle (Classes 6–8)',     'min_class' => 6,  'max_class' => 8,  'sort_order' => 2],
            ['code' => 'SEC',   'label' => 'Secondary',         'min_class' => 9,  'max_class' => 10, 'sort_order' => 3],
            ['code' => 'SrSEC', 'label' => 'Senior Secondary',  'min_class' => 11, 'max_class' => 12, 'sort_order' => 4],
        ];

        foreach ($categories as $cat) {
            ClassCategory::updateOrCreate(
                ['sahodaya_id' => null, 'code' => $cat['code']],
                array_merge($cat, ['is_active' => true]),
            );
        }

        $types = [
            ['code' => 'PRT', 'label' => 'Primary Teacher',           'sort_order' => 1],
            ['code' => 'TGT', 'label' => 'Trained Graduate Teacher',  'sort_order' => 2],
            ['code' => 'PGT', 'label' => 'Post Graduate Teacher',     'sort_order' => 3],
        ];

        foreach ($types as $type) {
            TeachingType::updateOrCreate(
                ['sahodaya_id' => null, 'code' => $type['code']],
                array_merge($type, ['is_active' => true]),
            );
        }

        app(MasterClassService::class)->ensureGlobalTemplates();
    }
}
