<?php

namespace Database\Seeders;

use App\Models\AgeCategory;
use App\Models\ClassCategory;
use App\Models\Designation;
use App\Models\Subject;
use App\Models\TeachingType;
use App\Services\Membership\MasterClassService;
use Illuminate\Database\Seeder;

class SahodayaMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'PRE',   'label' => 'Pre-Primary',      'min_class' => null, 'max_class' => null, 'sort_order' => 0],
            ['code' => 'PRY',   'label' => 'Primary',          'min_class' => 1,  'max_class' => 5,  'sort_order' => 1],
            ['code' => 'UP',    'label' => 'Upper Primary',    'min_class' => 6,  'max_class' => 8,  'sort_order' => 2],
            ['code' => 'SEC',   'label' => 'High School',      'min_class' => 9,  'max_class' => 10, 'sort_order' => 3],
            ['code' => 'SrSEC', 'label' => 'Higher Secondary', 'min_class' => 11, 'max_class' => 12, 'sort_order' => 4],
        ];

        foreach ($categories as $cat) {
            ClassCategory::updateOrCreate(
                ['sahodaya_id' => null, 'code' => $cat['code']],
                array_merge($cat, ['is_active' => true]),
            );
        }

        $types = [
            ['code' => 'PPT', 'label' => 'Pre-Primary Teacher',      'min_class' => null, 'max_class' => null, 'sort_order' => 0],
            ['code' => 'PRT', 'label' => 'Primary Teacher',          'min_class' => 1,    'max_class' => 5,    'sort_order' => 1],
            ['code' => 'TGT', 'label' => 'Trained Graduate Teacher', 'min_class' => 6,    'max_class' => 10,   'sort_order' => 2],
            ['code' => 'PGT', 'label' => 'Post Graduate Teacher',    'min_class' => 11,   'max_class' => 12,   'sort_order' => 3],
        ];

        foreach ($types as $type) {
            TeachingType::updateOrCreate(
                ['sahodaya_id' => null, 'code' => $type['code']],
                array_merge($type, ['is_active' => true]),
            );
        }

        $ageCategories = [
            ['code' => 'U10', 'label' => 'Under 10', 'max_age' => 10, 'cutoff_date' => '12-31', 'sort_order' => 0],
            ['code' => 'U12', 'label' => 'Under 12', 'max_age' => 12, 'cutoff_date' => '12-31', 'sort_order' => 1],
            ['code' => 'U14', 'label' => 'Under 14', 'max_age' => 14, 'cutoff_date' => '12-31', 'sort_order' => 2],
            ['code' => 'U16', 'label' => 'Under 16', 'max_age' => 16, 'cutoff_date' => '12-31', 'sort_order' => 3],
            ['code' => 'U19', 'label' => 'Under 19', 'max_age' => 19, 'cutoff_date' => '12-31', 'sort_order' => 4],
        ];

        foreach ($ageCategories as $age) {
            AgeCategory::updateOrCreate(
                ['sahodaya_id' => null, 'code' => $age['code']],
                array_merge($age, ['is_active' => true]),
            );
        }

        $designations = [
            ['code' => 'PRINCIPAL', 'label' => 'Principal', 'sort_order' => 0],
            ['code' => 'VP', 'label' => 'Vice Principal', 'sort_order' => 1],
            ['code' => 'HM', 'label' => 'Headmaster', 'sort_order' => 2],
            ['code' => 'COORD', 'label' => 'Academic Coordinator', 'sort_order' => 3],
            ['code' => 'HOD', 'label' => 'HOD', 'sort_order' => 4],
            ['code' => 'TEACHER', 'label' => 'Teacher', 'sort_order' => 5],
        ];

        foreach ($designations as $d) {
            Designation::updateOrCreate(
                ['sahodaya_id' => null, 'code' => $d['code']],
                array_merge($d, ['is_active' => true]),
            );
        }

        $subjects = [
            ['code' => 'ENG', 'label' => 'English'],
            ['code' => 'MAL', 'label' => 'Malayalam'],
            ['code' => 'HIN', 'label' => 'Hindi'],
            ['code' => 'MAT', 'label' => 'Mathematics'],
            ['code' => 'PHY', 'label' => 'Physics'],
            ['code' => 'CHE', 'label' => 'Chemistry'],
            ['code' => 'BIO', 'label' => 'Biology'],
            ['code' => 'CS', 'label' => 'Computer Science'],
            ['code' => 'AI', 'label' => 'Artificial Intelligence'],
            ['code' => 'SS', 'label' => 'Social Science'],
            ['code' => 'ECO', 'label' => 'Economics'],
            ['code' => 'ACC', 'label' => 'Accountancy'],
            ['code' => 'BS', 'label' => 'Business Studies'],
            ['code' => 'PE', 'label' => 'Physical Education'],
            ['code' => 'MUS', 'label' => 'Music'],
            ['code' => 'DAN', 'label' => 'Dance'],
            ['code' => 'ART', 'label' => 'Art'],
        ];

        foreach ($subjects as $i => $s) {
            Subject::updateOrCreate(
                ['sahodaya_id' => null, 'code' => $s['code']],
                array_merge($s, ['is_active' => true, 'sort_order' => $i]),
            );
        }

        app(MasterClassService::class)->ensureGlobalTemplates();
    }
}
