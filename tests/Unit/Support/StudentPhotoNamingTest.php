<?php

namespace Tests\Unit\Support;

use App\Support\StudentPhotoNaming;
use Tests\TestCase;

class StudentPhotoNamingTest extends TestCase
{
    public function test_filename_stem_replaces_slashes_with_underscores(): void
    {
        $this->assertSame('STU_26_0006', StudentPhotoNaming::filenameStem('STU/26/0006'));
        $this->assertSame('STU_26_0006.jpg', StudentPhotoNaming::expectedFilename('STU/26/0006'));
    }

    public function test_reg_no_candidates_accept_underscore_upload_names(): void
    {
        $candidates = StudentPhotoNaming::regNoCandidates('STU_26_0006');

        $this->assertContains('STU/26/0006', $candidates);
    }

    public function test_lookup_keys_from_zip_folder_path(): void
    {
        $keys = StudentPhotoNaming::lookupKeysFromZipEntry('STU/26/0006.jpg');

        $this->assertContains('STU/26/0006', $keys);
    }

    public function test_resolve_student_matches_by_full_name(): void
    {
        $student = new \App\Models\Student([
            'name' => 'Rahul Kumar',
            'reg_no' => 'STU/26/0010',
        ]);

        $resolved = StudentPhotoNaming::resolveStudent(collect([$student]), 'Rahul Kumar.jpg');

        $this->assertNotNull($resolved);
        $this->assertSame('name', $resolved['match']);
        $this->assertSame('Rahul Kumar', $resolved['student']->name);
    }

    public function test_resolve_student_matches_case_insensitive_name(): void
    {
        $student = new \App\Models\Student([
            'name' => 'Rahul Kumar',
            'reg_no' => 'STU/26/0010',
        ]);

        $resolved = StudentPhotoNaming::resolveStudent(collect([$student]), 'rahul kumar.png');

        $this->assertNotNull($resolved);
        $this->assertSame('name', $resolved['match']);
    }

    public function test_reg_no_candidates_accept_collapsed_paste_variants(): void
    {
        $candidates = StudentPhotoNaming::regNoCandidates('STU260006');

        $this->assertContains('STU/26/0006', $candidates);
    }

    public function test_resolve_student_matches_collapsed_reg_no_filename(): void
    {
        $student = new \App\Models\Student([
            'name' => 'Paste Student',
            'reg_no' => 'STU/26/0012',
        ]);

        $resolved = StudentPhotoNaming::resolveStudent(collect([$student]), 'STU260012.jpg');

        $this->assertNotNull($resolved);
        $this->assertSame('id', $resolved['match']);
    }

    public function test_normalize_reg_no_strips_separators(): void
    {
        $this->assertSame('stu260006', StudentPhotoNaming::normalizeRegNo('STU/26/0006'));
        $this->assertSame('stu260006', StudentPhotoNaming::normalizeRegNo('STU_26_0006'));
    }
}
