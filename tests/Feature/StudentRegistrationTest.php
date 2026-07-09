<?php

namespace Tests\Feature;

use App\Models\ClassCategory;
use App\Models\MasterClass;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Membership\MasterClassService;
use App\Services\Students\SchoolClassProvisioner;
use App\Services\Students\StudentRegistrationNumberGenerator;
use App\Support\AcademicYear;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\OpensStudentWindows;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;
    use OpensStudentWindows;

    private function schoolWithClass(): array
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'domain'    => 'test-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix'    => 'KNR',
        ]);

        $this->openStudentWindows($sahodaya);

        $tenant = Tenant::create([
            'id'            => (string) Str::uuid(),
            'type'          => 'school',
            'name'          => 'Test School',
            'domain'        => 'student-school.test',
            'parent_id'     => $sahodaya->id,
            'school_prefix' => 'TST',
            'is_active'     => true,
        ]);

        app(MasterClassService::class)->ensureForSahodaya($sahodaya->id);

        app(SchoolClassProvisioner::class)->ensureForSchool($tenant);

        $category = ClassCategory::where('code', 'SEC')->first();
        $class = SchoolClass::where('tenant_id', $tenant->id)->where('name', '10')->firstOrFail();

        return compact('tenant', 'category', 'class');
    }

    public function test_school_classes_are_provisioned_from_sahodaya_master(): void
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id'   => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
        ]);

        $school = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'Test School',
            'parent_id' => $sahodaya->id,
        ]);

        app(MasterClassService::class)->ensureForSahodaya($sahodaya->id);

        $created = app(SchoolClassProvisioner::class)->ensureForSchool($school);

        $this->assertGreaterThan(0, $created);
        $this->assertDatabaseHas('school_classes', [
            'tenant_id' => $school->id,
            'name'      => '10',
        ]);
        $this->assertDatabaseHas('school_classes', [
            'tenant_id' => $school->id,
            'name'      => '12',
        ]);
    }

    public function test_student_gets_sequential_registration_number(): void
    {
        ['tenant' => $tenant, 'class' => $class] = $this->schoolWithClass();

        $regNo = app(StudentRegistrationNumberGenerator::class)->generate($tenant->fresh());

        $yearSuffix = substr(explode('-', date('n') >= 4 ? date('Y').'-'.substr((string) (date('Y') + 1), -2) : (date('Y') - 1).'-'.substr(date('Y'), -2))[1], -2);
        $this->assertSame("STU/{$yearSuffix}/0001", $regNo);

        $student = Student::create([
            'tenant_id'        => $tenant->id,
            'school_class_id'  => $class->id,
            'name'             => 'Rahul Kumar',
            'reg_no'           => $regNo,
            'status'           => 'active',
        ]);

        $this->assertSame("STU/{$yearSuffix}/0001", $student->reg_no);

        $second = app(StudentRegistrationNumberGenerator::class)->generate($tenant->fresh());
        $this->assertSame("STU/{$yearSuffix}/0002", $second);
    }

    public function test_school_can_register_a_student_under_a_class(): void
    {
        ['tenant' => $tenant, 'class' => $class, 'category' => $category] = $this->schoolWithClass();

        $student = Student::create([
            'tenant_id'        => $tenant->id,
            'school_class_id'  => $class->id,
            'name'             => 'Rahul Kumar',
            'status'           => 'active',
        ]);

        $this->assertDatabaseHas('students', [
            'id'              => $student->id,
            'school_class_id' => $class->id,
            'name'            => 'Rahul Kumar',
        ]);

        $this->assertSame('10', $student->fresh()->class_label);
        $this->assertSame('Secondary', $student->fresh()->schoolClass->classCategory->label);
    }

    public function test_students_do_not_require_admission_number(): void
    {
        ['tenant' => $tenant, 'class' => $class] = $this->schoolWithClass();

        Student::create([
            'tenant_id'       => $tenant->id,
            'school_class_id' => $class->id,
            'name'            => 'First Student',
            'status'          => 'active',
        ]);

        Student::create([
            'tenant_id'       => $tenant->id,
            'school_class_id' => $class->id,
            'name'            => 'Second Student',
            'status'          => 'active',
        ]);

        $this->assertSame(2, Student::where('tenant_id', $tenant->id)->count());
    }

    public function test_bulk_csv_import_creates_students(): void
    {
        ['tenant' => $tenant, 'class' => $class] = $this->schoolWithClass();
        $tenant->update(['school_prefix' => 'TST']);

        $csv = "full_name,class_name,email\n"
            ."Rahul Kumar,10,rahul@example.com\n"
            ."Anita Shah,10,\n";

        $path = tempnam(sys_get_temp_dir(), 'students');
        file_put_contents($path, $csv);
        $file = new \Illuminate\Http\UploadedFile($path, 'students.csv', 'text/csv', null, true);

        $result = (new \App\Services\Students\StudentCsvImporter($tenant->fresh()))->import($file);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseHas('students', [
            'tenant_id'       => $tenant->id,
            'name'            => 'Rahul Kumar',
            'parent_email'    => 'rahul@example.com',
            'school_class_id' => $class->id,
        ]);
        $this->assertNotNull(Student::where('tenant_id', $tenant->id)->where('name', 'Rahul Kumar')->value('reg_no'));
        $this->assertDatabaseHas('students', [
            'tenant_id'    => $tenant->id,
            'name'         => 'Anita Shah',
            'parent_email' => null,
        ]);
    }

    public function test_bulk_csv_import_is_all_or_nothing(): void
    {
        ['tenant' => $tenant, 'class' => $class] = $this->schoolWithClass();
        $tenant->update(['school_prefix' => 'TST']);

        // One good row, one row with an unknown class — the whole file must be rejected.
        $csv = "full_name,class_name,email\n"
            ."Rahul Kumar,10,rahul@example.com\n"
            ."Anita Shah,NoSuchClass,\n";

        $path = tempnam(sys_get_temp_dir(), 'students');
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'students.csv', 'text/csv', null, true);

        $result = (new \App\Services\Students\StudentCsvImporter($tenant->fresh()))->import($file);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['imported']);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame(0, Student::where('tenant_id', $tenant->id)->count());
    }

    public function test_bulk_xlsx_import_creates_students(): void
    {
        ['tenant' => $tenant, 'class' => $class] = $this->schoolWithClass();
        $tenant->update(['school_prefix' => 'TST']);

        $xlsx = \App\Services\Spreadsheet\SpreadsheetWriter::xlsx([
            ['full_name', 'class_name', 'email'],
            ['Rahul Kumar', '10', 'rahul@example.com'],
        ]);

        $path = tempnam(sys_get_temp_dir(), 'students').'.xlsx';
        file_put_contents($path, $xlsx);
        $file = new UploadedFile($path, 'students.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $result = (new \App\Services\Students\StudentCsvImporter($tenant->fresh()))->import($file);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseHas('students', [
            'tenant_id'       => $tenant->id,
            'name'            => 'Rahul Kumar',
            'school_class_id' => $class->id,
        ]);
    }

    public function test_student_import_preview_and_store_via_http(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['tenant' => $school, 'class' => $class] = $this->schoolWithClass();

        $admin = \App\Models\User::factory()->create([
            'tenant_id'         => $school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $csv = "full_name,class_name,gender,dob,email\n"
            ."Rahul Kumar,10,male,2012-05-01,\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csv, 'text/csv');

        $this->actingAs($admin)
            ->postJson("/school-admin/{$school->id}/students/import/preview", ['file' => $file])
            ->assertOk()
            ->assertJsonPath('total_rows', 1)
            ->assertJsonPath('errors', [])
            ->assertJsonPath('valid.0.name', 'Rahul Kumar');

        $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/students/import", ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('students', [
            'tenant_id'       => $school->id,
            'name'            => 'Rahul Kumar',
            'school_class_id' => $class->id,
        ]);
    }

    public function test_student_import_rejects_empty_upload(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['tenant' => $school] = $this->schoolWithClass();

        $admin = \App\Models\User::factory()->create([
            'tenant_id'         => $school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $file = UploadedFile::fake()->create('empty.csv', 0, 'text/csv');

        $this->actingAs($admin)
            ->postJson("/school-admin/{$school->id}/students/import/preview", ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_student_photo_zip_matches_underscore_student_id_filenames(): void
    {
        Storage::fake('shared');
        config(['filesystems.upload_disk' => 'shared']);

        $this->seed(RolesAndPermissionsSeeder::class);
        ['tenant' => $school, 'class' => $class] = $this->schoolWithClass();

        $admin = \App\Models\User::factory()->create([
            'tenant_id'         => $school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $student = Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $class->id,
            'name'            => 'Photo Zip Student',
            'status'          => 'active',
            'reg_no'          => 'STU/26/0006',
            'admission_number'=> app(StudentRegistrationNumberGenerator::class)->generate($school),
        ]);

        $zipPath = tempnam(sys_get_temp_dir(), 'student_photos').'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('STU_26_0006.jpg', base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k='
        ));
        $zip->close();

        $zipUpload = new UploadedFile($zipPath, 'photos.zip', 'application/zip', null, true);

        $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/students/photos-zip", ['zip' => $zipUpload])
            ->assertRedirect()
            ->assertSessionHas('success');

        $student->refresh();
        $this->assertNotNull($student->photo);
        $this->assertTrue(Storage::disk('shared')->exists($student->photo));

        @unlink($zipPath);
    }

    public function test_student_photo_zip_matches_folder_path_student_id(): void
    {
        Storage::fake('shared');
        config(['filesystems.upload_disk' => 'shared']);

        $this->seed(RolesAndPermissionsSeeder::class);
        ['tenant' => $school, 'class' => $class] = $this->schoolWithClass();

        $admin = \App\Models\User::factory()->create([
            'tenant_id'         => $school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $student = Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $class->id,
            'name'            => 'Folder Path Student',
            'status'          => 'active',
            'reg_no'          => 'STU/26/0007',
            'admission_number'=> app(StudentRegistrationNumberGenerator::class)->generate($school),
        ]);

        $jpeg = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k='
        );

        $zipPath = tempnam(sys_get_temp_dir(), 'student_photos').'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('STU/26/0007.jpg', $jpeg);
        $zip->close();

        $zipUpload = new UploadedFile($zipPath, 'photos.zip', 'application/zip', null, true);

        $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/students/photos-zip", ['zip' => $zipUpload])
            ->assertRedirect()
            ->assertSessionHas('success');

        $student->refresh();
        $this->assertNotNull($student->photo);

        @unlink($zipPath);
    }

    public function test_student_photo_upload_matches_by_student_name(): void
    {
        Storage::fake('shared');
        config(['filesystems.upload_disk' => 'shared']);

        $this->seed(RolesAndPermissionsSeeder::class);
        ['tenant' => $school, 'class' => $class] = $this->schoolWithClass();

        $admin = \App\Models\User::factory()->create([
            'tenant_id'         => $school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $student = Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $class->id,
            'name'            => 'Rahul Kumar',
            'status'          => 'active',
            'reg_no'          => 'STU/26/0011',
            'admission_number'=> app(StudentRegistrationNumberGenerator::class)->generate($school),
        ]);

        $photo = UploadedFile::fake()->image('Rahul Kumar.jpg');

        $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/students/photos-zip", ['photos' => [$photo]])
            ->assertRedirect()
            ->assertSessionHas('success');

        $student->refresh();
        $this->assertNotNull($student->photo);
        $this->assertTrue(Storage::disk('shared')->exists($student->photo));
    }

    public function test_import_template_uses_school_class_names(): void
    {
        ['tenant' => $tenant, 'class' => $class] = $this->schoolWithClass();

        $firstClass = SchoolClass::where('tenant_id', $tenant->id)->orderBy('display_order')->firstOrFail();

        $csv = (new \App\Services\Students\StudentCsvImporter($tenant))->templateCsvForSchool();

        $this->assertStringContainsString('full_name,class_name,email', $csv);
        $this->assertStringContainsString($firstClass->name, $csv);
        $this->assertStringContainsString('Rahul Kumar', $csv);
    }

    public function test_student_photo_url_uses_serve_route_for_tenant_public_disk(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['tenant' => $school, 'class' => $class] = $this->schoolWithClass();

        $relative = 'students/'.$school->id.'/tenant-public.jpg';
        $tenantDir = base_path('storage/tenant'.$school->id.'/app/public/'.$relative);
        @mkdir(dirname($tenantDir), 0777, true);
        file_put_contents($tenantDir, 'fake-image-bytes');

        $student = Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $class->id,
            'name'            => 'Tenant Public Photo',
            'status'          => 'active',
            'admission_number'=> app(StudentRegistrationNumberGenerator::class)->generate($school),
            'photo'           => $relative,
        ]);

        $photoUrl = $student->fresh()->photoUrl();
        $this->assertStringContainsString("/school-admin/{$school->id}/students/{$student->id}/photo", $photoUrl);

        $admin = \App\Models\User::factory()->create([
            'tenant_id'         => $school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $this->actingAs($admin)
            ->get("/school-admin/{$school->id}/students/{$student->id}/photo")
            ->assertOk();

        @unlink($tenantDir);
    }

    public function test_student_photo_url_uses_serve_route_and_streams_from_s3(): void
    {
        Storage::fake('s3');
        config(['filesystems.upload_disk' => 's3']);

        $this->seed(RolesAndPermissionsSeeder::class);
        ['tenant' => $school, 'class' => $class] = $this->schoolWithClass();

        $admin = \App\Models\User::factory()->create([
            'tenant_id'         => $school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $student = Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $class->id,
            'name'            => 'Photo Student',
            'status'          => 'active',
            'admission_number'=> app(StudentRegistrationNumberGenerator::class)->generate($school),
        ]);

        $path = 'students/'.$school->id.'/avatar.jpg';
        Storage::disk('s3')->put($path, 'fake-image-bytes');
        $student->update(['photo' => $path]);

        $photoUrl = $student->fresh()->photoUrl();
        $this->assertNotNull($photoUrl);
        $this->assertStringContainsString("/school-admin/{$school->id}/students/{$student->id}/photo", $photoUrl);

        $this->actingAs($admin)
            ->get("/school-admin/{$school->id}/students/{$student->id}/photo")
            ->assertOk();
    }

    public function test_student_photo_upload_stores_on_shared_disk(): void
    {
        Storage::fake('shared');
        config(['filesystems.upload_disk' => 'shared']);

        $this->seed(RolesAndPermissionsSeeder::class);
        ['tenant' => $school, 'class' => $class] = $this->schoolWithClass();

        $admin = \App\Models\User::factory()->create([
            'tenant_id'         => $school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $student = Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $class->id,
            'name'            => 'Upload Student',
            'status'          => 'active',
            'admission_number'=> app(StudentRegistrationNumberGenerator::class)->generate($school),
        ]);

        $file = UploadedFile::fake()->image('student.jpg');

        $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/students/{$student->id}/photo", ['photo' => $file])
            ->assertRedirect();

        $student->refresh();
        $this->assertNotNull($student->photo);
        Storage::disk('shared')->assertExists($student->photo);

        $this->actingAs($admin)
            ->get("/school-admin/{$school->id}/students/{$student->id}/photo")
            ->assertOk();
    }
}
