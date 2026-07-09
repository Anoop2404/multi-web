<?php

namespace Tests\Feature;

use App\Models\SahodayaProfile;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\UploadedFileBackup;
use App\Models\User;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Spreadsheet\SpreadsheetWriter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherImportTest extends TestCase
{
    use RefreshDatabase;

    private function schoolWithAdmin(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'teacher-import-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'KNR',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Test School',
            'domain' => 'teacher-import-school.test',
            'parent_id' => $sahodaya->id,
            'school_prefix' => 'TST',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        return compact('sahodaya', 'school', 'admin');
    }

    public function test_teacher_import_creates_teachers_and_backup(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'admin' => $admin] = $this->schoolWithAdmin();

        $resolver = app(EffectiveMasterDataResolver::class);
        $teachingType = $resolver->teachingTypes($sahodaya->id)->first()->label;
        $subjects = $resolver->subjects($sahodaya->id)->take(2)->pluck('label')->implode('; ');

        $csv = "name,email,mobile,gender,designation,teaching_type,subjects,qualification,date_of_joining\n"
            ."Anita Menon,anita.import.test@example.com,9876543210,female,,{$teachingType},{$subjects},M.Sc,2020-06-01\n";

        $path = tempnam(sys_get_temp_dir(), 'teachers');
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'teachers.csv', 'text/csv', null, true);

        $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/teachers/import", ['file' => $file])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('teachers', [
            'tenant_id' => $school->id,
            'name' => 'Anita Menon',
            'email' => 'anita.import.test@example.com',
        ]);

        $teacher = Teacher::where('email', 'anita.import.test@example.com')->first();
        $this->assertNotNull($teacher->user_id, 'Portal login should be provisioned.');

        $backup = UploadedFileBackup::where('school_id', $school->id)->where('purpose', 'teacher_import')->first();
        $this->assertNotNull($backup, 'Uploaded file should be kept for reference.');
        $this->assertSame('success', $backup->status);
        $this->assertSame(1, $backup->imported_count);
        $this->assertTrue($backup->existsOnDisk());
    }

    public function test_teacher_import_is_all_or_nothing(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'admin' => $admin] = $this->schoolWithAdmin();

        $resolver = app(EffectiveMasterDataResolver::class);
        $teachingType = $resolver->teachingTypes($sahodaya->id)->first()->label;
        $subjects = $resolver->subjects($sahodaya->id)->take(2)->pluck('label')->implode('; ');

        // Second row has an unrecognized teaching_type — the whole file must be rejected.
        $csv = "name,email,mobile,gender,designation,teaching_type,subjects,qualification,date_of_joining\n"
            ."Good Teacher,good.import.test@example.com,9876543211,female,,{$teachingType},{$subjects},M.Sc,2020-06-01\n"
            ."Bad Teacher,bad.import.test@example.com,9876543212,male,,NOT_A_REAL_TYPE,{$subjects},M.Sc,2020-06-01\n";

        $path = tempnam(sys_get_temp_dir(), 'teachers');
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'teachers.csv', 'text/csv', null, true);

        $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/teachers/import", ['file' => $file])
            ->assertSessionHas('error')
            ->assertSessionHas('importErrors');

        $this->assertSame(0, Teacher::where('tenant_id', $school->id)->count());
        $this->assertDatabaseMissing('users', ['email' => 'good.import.test@example.com']);

        $backup = UploadedFileBackup::where('school_id', $school->id)->where('purpose', 'teacher_import')->first();
        $this->assertSame('failed', $backup->status);
        $this->assertSame(0, $backup->imported_count);
        $this->assertGreaterThan(0, $backup->error_count);
    }

    public function test_teacher_xlsx_import_works(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'admin' => $admin] = $this->schoolWithAdmin();

        $resolver = app(EffectiveMasterDataResolver::class);
        $teachingType = $resolver->teachingTypes($sahodaya->id)->first()->label;
        $subjects = $resolver->subjects($sahodaya->id)->take(2)->pluck('label')->implode('; ');

        $xlsx = SpreadsheetWriter::xlsx([
            ['name', 'email', 'mobile', 'gender', 'designation', 'teaching_type', 'subjects', 'qualification', 'date_of_joining'],
            ['Xlsx Teacher', 'xlsx.import.test@example.com', '9876543213', 'female', '', $teachingType, $subjects, 'M.Sc', '2020-06-01'],
        ]);

        $path = tempnam(sys_get_temp_dir(), 'teachers').'.xlsx';
        file_put_contents($path, $xlsx);
        $file = new UploadedFile($path, 'teachers.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/teachers/import", ['file' => $file])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('teachers', [
            'tenant_id' => $school->id,
            'name' => 'Xlsx Teacher',
            'email' => 'xlsx.import.test@example.com',
        ]);
    }
}
