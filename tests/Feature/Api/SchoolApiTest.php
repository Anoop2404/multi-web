<?php

namespace Tests\Feature\Api;

use App\Models\ClassCategory;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Membership\MasterClassService;
use App\Services\Students\SchoolClassProvisioner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\OpensStudentWindows;
use Tests\TestCase;

class SchoolApiTest extends TestCase
{
    use RefreshDatabase;
    use OpensStudentWindows;

    private function schoolContext(): array
    {
        $this->seed([SahodayaMasterDataSeeder::class, RolesAndPermissionsSeeder::class]);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'KNR Sahodaya',
            'domain'    => 'knr-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix'    => 'KNR',
        ]);

        $this->openStudentWindows($sahodaya);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Govt HS',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'GHS',
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        app(MasterClassService::class)->ensureForSahodaya($sahodaya->id);
        app(SchoolClassProvisioner::class)->ensureForSchool($school);

        $user = User::factory()->create([
            'tenant_id'         => $school->id,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('school_admin');

        $token = $user->createToken('test')->plainTextToken;
        $class = SchoolClass::where('tenant_id', $school->id)->where('name', '10')->firstOrFail();

        return compact('sahodaya', 'school', 'user', 'token', 'class');
    }

    private function auth(string $token): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_school_dashboard_requires_matching_tenant(): void
    {
        ['school' => $school, 'token' => $token] = $this->schoolContext();

        $this->auth($token)
            ->getJson("/api/v1/school/{$school->id}/dashboard")
            ->assertOk();

        $this->auth($token)
            ->getJson('/api/v1/school/'.Str::uuid().'/dashboard')
            ->assertForbidden();
    }

    public function test_school_admin_can_crud_students(): void
    {
        ['school' => $school, 'token' => $token, 'class' => $class] = $this->schoolContext();

        $create = $this->auth($token)->postJson("/api/v1/school/{$school->id}/students", [
            'name'            => 'Alice',
            'gender'          => 'female',
            'school_class_id' => $class->id,
        ]);

        $create->assertCreated();
        $studentId = $create->json('data.id');

        $this->auth($token)
            ->getJson("/api/v1/school/{$school->id}/students")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alice');

        $this->auth($token)
            ->putJson("/api/v1/school/{$school->id}/students/{$studentId}", [
                'name'            => 'Alice Updated',
                'gender'          => 'female',
                'school_class_id' => $class->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Alice Updated');

        $this->auth($token)
            ->deleteJson("/api/v1/school/{$school->id}/students/{$studentId}")
            ->assertOk();

        $this->assertDatabaseMissing('students', ['id' => $studentId]);
    }

    public function test_school_admin_can_upload_and_serve_student_photo(): void
    {
        Storage::fake('s3');

        ['school' => $school, 'token' => $token, 'class' => $class] = $this->schoolContext();

        $student = Student::create([
            'tenant_id'       => $school->id,
            'name'            => 'Bob',
            'gender'          => 'male',
            'school_class_id' => $class->id,
            'status'          => 'active',
        ]);

        $this->auth($token)
            ->post("/api/v1/school/{$school->id}/students/{$student->id}/photo", [
                'photo' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertOk();

        $student->refresh();
        $this->assertNotNull($student->photo);

        $this->auth($token)
            ->get("/api/v1/school/{$school->id}/students/{$student->id}/photo")
            ->assertOk();
    }
}
