<?php

namespace Tests\Feature;

use App\Models\ClassCategory;
use App\Models\DataChangeLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\UploadedFileBackup;
use App\Services\Audit\DataChangeLogger;
use App\Services\Audit\UploadBackupService;
use App\Services\Students\StudentCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function schoolWithClass(): array
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id'            => (string) Str::uuid(),
            'type'          => 'school',
            'name'          => 'Test School',
            'parent_id'     => $sahodaya->id,
            'school_prefix' => 'TST',
            'is_active'     => true,
        ]);

        $category = ClassCategory::create([
            'code'       => 'SEC',
            'label'      => 'Secondary',
            'min_class'  => 9,
            'max_class'  => 10,
            'sort_order' => 1,
        ]);

        $class = SchoolClass::create([
            'tenant_id'         => $school->id,
            'class_category_id' => $category->id,
            'name'              => '10',
            'display_order'     => 10,
        ]);

        return compact('school', 'class');
    }

    public function test_upload_backup_stores_private_copy(): void
    {
        Storage::fake('local');

        ['school' => $school] = $this->schoolWithClass();
        $file = UploadedFile::fake()->create('students.csv', 10, 'text/csv');

        $backup = app(UploadBackupService::class)->store(
            $file,
            'student_import',
            $school->id,
        );

        $this->assertDatabaseHas('uploaded_file_backups', [
            'id'           => $backup->id,
            'school_id'    => $school->id,
            'purpose'      => 'student_import',
            'original_name'=> 'students.csv',
        ]);
        Storage::disk('local')->assertExists($backup->storage_path);
    }

    public function test_student_import_creates_backup_and_log(): void
    {
        Storage::fake('local');

        ['school' => $school, 'class' => $class] = $this->schoolWithClass();

        $csv = "full_name,class_name,email\nAda Lovelace,{$class->name},\n";
        $path = tempnam(sys_get_temp_dir(), 'audit');
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'students.csv', 'text/csv', null, true);

        $backup = app(UploadBackupService::class)->store($file, 'student_import', $school->id);
        $result = (new StudentCsvImporter($school))->import($file);

        app(DataChangeLogger::class)->event(
            'imported',
            "Student CSV import: {$result['imported']} added",
            $school->id,
            'students',
            null,
            ['imported' => $result['imported'], 'backup_id' => $backup->id],
        );

        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseHas('uploaded_file_backups', ['id' => $backup->id]);
        $this->assertDatabaseHas('data_change_logs', [
            'school_id' => $school->id,
            'action'    => 'imported',
            'log_name'  => 'students',
        ]);
    }

    public function test_student_create_logs_change(): void
    {
        ['school' => $school, 'class' => $class] = $this->schoolWithClass();

        $student = Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $class->id,
            'name'            => 'Logged Student',
            'status'          => 'active',
        ]);

        app(DataChangeLogger::class)->created($student, 'Student registered', $school->id, 'students');

        $this->assertDatabaseHas('data_change_logs', [
            'school_id'    => $school->id,
            'action'       => 'created',
            'subject_type' => $student->getMorphClass(),
            'subject_id'   => $student->id,
        ]);
    }

    public function test_data_change_logger_diff(): void
    {
        $diff = DataChangeLogger::diff(
            ['name' => 'Old', 'status' => 'active'],
            ['name' => 'New', 'status' => 'active'],
        );

        $this->assertSame(['name' => ['old' => 'Old', 'new' => 'New']], $diff);
    }
}
