<?php

namespace Tests\Unit\Services\Training;

use App\Models\SahodayaProfile;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Services\Training\TeacherTrainingEligibilityService;
use App\Services\Training\TrainingRegistrationCsvImporter;
use App\Services\Training\TrainingRegistrationLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingRegistrationCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: TrainingProgram, 2: Teacher, 3: TrainingRegistrationCsvImporter} */
    private function seedProgram(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sahodaya',
            'type' => 'sahodaya',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'teacher_registration_enabled' => true,
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'School',
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'is_active' => true,
            'membership_status' => 'approved',
        ]);
        $program = TrainingProgram::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Workshop',
            'status' => 'published',
            'fee_type' => 'none',
            'require_verified_teachers' => false,
            'registration_open' => now()->subDay()->toDateString(),
            'registration_close' => now()->addDays(5)->toDateString(),
        ]);
        $teacher = Teacher::create([
            'tenant_id' => $school->id,
            'name' => 'Anita Menon',
            'email' => 'anita@school.edu',
            'login_code' => 'TCH-100',
            'employee_code' => 'EMP-100',
            'status' => 'active',
        ]);

        $importer = new TrainingRegistrationCsvImporter(
            $school,
            app(TeacherTrainingEligibilityService::class),
            app(TrainingRegistrationLifecycle::class),
        );

        return [$school, $program, $teacher, $importer];
    }

    public function test_imports_by_email_and_skips_existing(): void
    {
        [, $program, $teacher, $importer] = $this->seedProgram();

        $path = tempnam(sys_get_temp_dir(), 'trn_imp');
        file_put_contents($path, "email,login_code,employee_code,name\nanita@school.edu,,,Anita Menon\n");

        $result = $importer->importFromPath($path, $program);
        @unlink($path);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseHas('training_registrations', [
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'registration_source' => 'school',
        ]);

        $path2 = tempnam(sys_get_temp_dir(), 'trn_imp');
        file_put_contents($path2, "email\nanita@school.edu\n");
        $again = $importer->importFromPath($path2, $program);
        @unlink($path2);

        $this->assertTrue($again['success']);
        $this->assertSame(0, $again['imported']);
        $this->assertSame(1, TrainingRegistration::where('program_id', $program->id)->count());
    }

    public function test_reports_unknown_teacher_per_row(): void
    {
        [, $program, , $importer] = $this->seedProgram();

        $path = tempnam(sys_get_temp_dir(), 'trn_imp');
        file_put_contents($path, "email\nmissing@school.edu\n");

        $result = $importer->importFromPath($path, $program);
        @unlink($path);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['imported']);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame(2, $result['errors'][0]['row']);
    }

    public function test_imports_by_login_code(): void
    {
        [, $program, $teacher, $importer] = $this->seedProgram();

        $path = tempnam(sys_get_temp_dir(), 'trn_imp');
        file_put_contents($path, "login_code\nTCH-100\n");

        $result = $importer->importFromPath($path, $program);
        @unlink($path);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseHas('training_registrations', [
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_export_rows_include_nominations(): void
    {
        [$school, $program, $teacher, $importer] = $this->seedProgram();

        TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id' => $school->id,
            'status' => 'confirmed',
            'registration_source' => 'school',
        ]);

        $rows = $importer->exportRows($program);

        $this->assertSame('name', $rows[0][0]);
        $this->assertSame('Anita Menon', $rows[1][0]);
        $this->assertSame('anita@school.edu', $rows[1][1]);
    }
}
