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

        // Full CBSE Class XII subject master, correctly categorized (see Subject::CATEGORY_*):
        // Category I (language) — every student picks 2, e.g. English Core + Malayalam.
        // Category II (science/commerce/humanities) — stream electives, student picks 3
        //   from their stream's pool (which specific 3 is the student's choice, not fixed).
        // Category III (skill) — a fully optional additional/6th subject, any stream.
        // Existing codes (ENG/MAL/HIN/MAT/PHY/CHE/BIO/CS/AI/SS/ECO/ACC/BS/PE/MUS/DAN/ART) are
        // kept exactly as-is so already-provisioned Sahodayas don't get duplicate rows —
        // updateOrCreate() below only adds the category (and corrects the 'English'/'Hindi'
        // labels to their precise CBSE names) without touching anything referencing the code.
        $subjects = [
            // Category I — Languages
            ['code' => 'ENG',  'label' => 'English Core',        'category' => Subject::CATEGORY_LANGUAGE],
            ['code' => 'ENGE', 'label' => 'English Elective',    'category' => Subject::CATEGORY_LANGUAGE],
            ['code' => 'HIN',  'label' => 'Hindi Core',          'category' => Subject::CATEGORY_LANGUAGE],
            ['code' => 'HINE', 'label' => 'Hindi Elective',      'category' => Subject::CATEGORY_LANGUAGE],
            ['code' => 'MAL',  'label' => 'Malayalam',           'category' => Subject::CATEGORY_LANGUAGE],
            ['code' => 'SKT',  'label' => 'Sanskrit',            'category' => Subject::CATEGORY_LANGUAGE],
            ['code' => 'ARB',  'label' => 'Arabic',              'category' => Subject::CATEGORY_LANGUAGE],

            // Category II — Science electives
            ['code' => 'PHY', 'label' => 'Physics',              'category' => Subject::CATEGORY_SCIENCE],
            ['code' => 'CHE', 'label' => 'Chemistry',             'category' => Subject::CATEGORY_SCIENCE],
            ['code' => 'BIO', 'label' => 'Biology',               'category' => Subject::CATEGORY_SCIENCE],
            ['code' => 'MAT', 'label' => 'Mathematics',           'category' => Subject::CATEGORY_SCIENCE],
            ['code' => 'CS',  'label' => 'Computer Science',      'category' => Subject::CATEGORY_SCIENCE],
            ['code' => 'IP',  'label' => 'Informatics Practices', 'category' => Subject::CATEGORY_SCIENCE],

            // Category II — Commerce electives
            ['code' => 'ACC', 'label' => 'Accountancy',           'category' => Subject::CATEGORY_COMMERCE],
            ['code' => 'BS',  'label' => 'Business Studies',      'category' => Subject::CATEGORY_COMMERCE],
            ['code' => 'ECO', 'label' => 'Economics',             'category' => Subject::CATEGORY_COMMERCE],
            ['code' => 'BA',  'label' => 'Business Administration', 'category' => Subject::CATEGORY_COMMERCE],

            // Category II — Humanities electives
            ['code' => 'HIS',  'label' => 'History',              'category' => Subject::CATEGORY_HUMANITIES],
            ['code' => 'POL',  'label' => 'Political Science',    'category' => Subject::CATEGORY_HUMANITIES],
            ['code' => 'GEO',  'label' => 'Geography',            'category' => Subject::CATEGORY_HUMANITIES],
            ['code' => 'SOC',  'label' => 'Sociology',            'category' => Subject::CATEGORY_HUMANITIES],
            ['code' => 'PSY',  'label' => 'Psychology',           'category' => Subject::CATEGORY_HUMANITIES],
            ['code' => 'PHIL', 'label' => 'Philosophy',           'category' => Subject::CATEGORY_HUMANITIES],
            // Social Science (below-10 combined subject, not a Class XII stream elective) —
            // kept uncategorized so it doesn't misleadingly show up in a stream's Category II pool.
            ['code' => 'SS', 'label' => 'Social Science', 'category' => null],

            // Category III — Skill subjects (optional additional/6th subject, any stream)
            ['code' => 'HSC', 'label' => 'Home Science',          'category' => Subject::CATEGORY_SKILL],
            ['code' => 'FS',  'label' => 'Fashion Studies',       'category' => Subject::CATEGORY_SKILL],
            ['code' => 'PE',  'label' => 'Physical Education',    'category' => Subject::CATEGORY_SKILL],
            ['code' => 'AI',  'label' => 'Artificial Intelligence', 'category' => Subject::CATEGORY_SKILL],
            ['code' => 'MUS', 'label' => 'Music',                 'category' => Subject::CATEGORY_SKILL],
            ['code' => 'DAN', 'label' => 'Dance',                 'category' => Subject::CATEGORY_SKILL],
            ['code' => 'ART', 'label' => 'Art',                   'category' => Subject::CATEGORY_SKILL],
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
