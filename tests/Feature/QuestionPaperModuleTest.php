<?php

namespace Tests\Feature;

use App\Models\QuestionPaper;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Students\SchoolClassProvisioner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuestionPaperModuleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $school;

    private User $admin;

    private SchoolClass $schoolClass;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SahodayaMasterDataSeeder::class);
        Storage::fake('shared');

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Question Paper Sahodaya',
            'domain' => 'question-paper-sahodaya.test',
            'is_active' => true,
        ]);

        $this->school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Question Paper School',
            'domain' => 'question-paper-school.test',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->school->id,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $this->admin->assignRole('school_admin');

        app(SchoolClassProvisioner::class)->ensureForSchool($this->school);
        $this->schoolClass = SchoolClass::where('tenant_id', $this->school->id)->where('name', '8')->firstOrFail();
        $this->subject = Subject::where('code', 'MAT')->firstOrFail();
    }

    public function test_teacher_can_upload_and_edit_only_their_own_question_paper(): void
    {
        [$teacherUser, $teacher] = $this->makeTeacher('first.teacher@example.com');
        [$otherUser] = $this->makeTeacher('other.teacher@example.com');

        $this->actingAs($teacherUser)
            ->post(route('portal.teacher.question-papers.store', ['tenantId' => $this->school->id]), [
                'title' => 'First Term Mathematics',
                'school_class_id' => $this->schoolClass->id,
                'subject_id' => $this->subject->id,
                'academic_year' => '2026-27',
                'exam_name' => 'First Term',
                'files' => [UploadedFile::fake()->create('mathematics.pdf', 120, 'application/pdf')],
            ])
            ->assertSessionHas('success');

        $paper = QuestionPaper::with('files')->firstOrFail();
        $this->assertSame($teacher->id, $paper->teacher_id);
        $this->assertSame($this->school->id, $paper->school_id);
        $this->assertSame('Mathematics', $paper->subject_name);
        $this->assertCount(1, $paper->files);
        Storage::disk('shared')->assertExists($paper->files->first()->file_path);

        $this->actingAs($teacherUser)
            ->get(route('portal.teacher.question-papers.files.preview', ['tenantId' => $this->school->id, 'paper' => $paper->id, 'file' => $paper->files->first()->id]))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=mathematics.pdf');

        $this->actingAs($teacherUser)
            ->put(route('portal.teacher.question-papers.update', ['tenantId' => $this->school->id, 'paper' => $paper->id]), [
                'title' => 'Revised First Term Mathematics',
                'school_class_id' => $this->schoolClass->id,
                'subject_id' => $this->subject->id,
                'academic_year' => '2026-27',
                'exam_name' => 'First Term',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('question_papers', [
            'id' => $paper->id,
            'title' => 'Revised First Term Mathematics',
        ]);

        $this->actingAs($otherUser)
            ->put(route('portal.teacher.question-papers.update', ['tenantId' => $this->school->id, 'paper' => $paper->id]), [
                'title' => 'Unauthorized change',
                'school_class_id' => $this->schoolClass->id,
                'subject_id' => $this->subject->id,
                'academic_year' => '2026-27',
            ])
            ->assertNotFound();

        $this->actingAs($otherUser)
            ->get(route('portal.teacher.question-papers.files.download', ['tenantId' => $this->school->id, 'paper' => $paper->id, 'file' => $paper->files->first()->id]))
            ->assertNotFound();
    }

    public function test_school_admin_can_view_and_download_all_school_question_papers(): void
    {
        [, $teacher] = $this->makeTeacher('library.teacher@example.com');
        $path = UploadedFile::fake()->create('science.docx', 80, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->store("schools/{$this->school->id}/question-papers/{$teacher->id}", 'shared');

        $paper = QuestionPaper::create([
            'school_id' => $this->school->id,
            'teacher_id' => $teacher->id,
            'school_class_id' => $this->schoolClass->id,
            'class_name' => $this->schoolClass->name,
            'subject_id' => $this->subject->id,
            'subject_name' => $this->subject->label,
            'academic_year' => '2026-27',
            'title' => 'Admin Library Paper',
            'exam_name' => 'Annual Exam',
            'uploaded_by_user_id' => $teacher->user_id,
        ]);

        $file = $paper->files()->create([
            'file_path' => $path,
            'storage_disk' => 'shared',
            'original_name' => 'science.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => 81920,
        ]);

        $this->actingAs($this->admin)
            ->get(route('school.question-papers.index', [
                'tenantId' => $this->school->id,
                'school_class_id' => $this->schoolClass->id,
                'subject_id' => $this->subject->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('School/QuestionPapers/Index', false)
                ->has('papers.data', 1)
                ->where('papers.data.0.id', $paper->id)
                ->where('papers.data.0.teacher.name', $teacher->name));

        $this->actingAs($this->admin)
            ->get(route('school.question-papers.download', ['tenantId' => $this->school->id, 'paper' => $paper->id, 'file' => $file->id]))
            ->assertOk()
            ->assertDownload('science.docx');
    }

    /**
     * The subject picker intentionally shows the school's full subject list, not just a
     * teacher's own assignments — teachers routinely archive material for subjects other
     * than their own. Only class assignment is still enforced (a teacher can't file a
     * paper under a class they have no access to at all).
     */
    public function test_teacher_with_subject_assignments_can_still_upload_for_a_different_subject(): void
    {
        [$teacherUser] = $this->makeTeacher('assigned.teacher@example.com');
        $otherSubject = Subject::where('code', 'PHY')->firstOrFail();

        $this->actingAs($teacherUser)
            ->post(route('portal.teacher.question-papers.store', ['tenantId' => $this->school->id]), [
                'title' => 'Physics Paper',
                'school_class_id' => $this->schoolClass->id,
                'subject_id' => $otherSubject->id,
                'academic_year' => '2026-27',
                'files' => [UploadedFile::fake()->create('physics.pdf', 50, 'application/pdf')],
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('question_papers', 1);
    }

    /**
     * A teacher with no subject/class assigned to their profile (common for freshly
     * created or incompletely provisioned teacher records) must still be able to upload —
     * they just get the school's full subject/class lists instead of a filtered-to-mine
     * one, rather than being blocked outright.
     */
    public function test_teacher_with_no_assignments_can_upload_for_any_subject_or_class(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->school->id,
            'email' => 'unassigned.teacher@example.com',
            'must_change_password' => false,
        ]);
        $user->assignRole('teacher');

        Teacher::create([
            'tenant_id' => $this->school->id,
            'user_id' => $user->id,
            'name' => 'Unassigned Teacher',
            'email' => 'unassigned.teacher@example.com',
            'subject_ids' => [],
            'status' => 'active',
        ]);

        $anySubject = Subject::where('code', 'PHY')->firstOrFail();

        $this->actingAs($user)
            ->post(route('portal.teacher.question-papers.store', ['tenantId' => $this->school->id]), [
                'title' => 'Physics Paper',
                'school_class_id' => $this->schoolClass->id,
                'subject_id' => $anySubject->id,
                'academic_year' => '2026-27',
                'files' => [UploadedFile::fake()->create('physics.pdf', 50, 'application/pdf')],
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('question_papers', 1);
    }

    public function test_non_leadership_school_staff_cannot_open_the_school_wide_library(): void
    {
        $staff = User::factory()->create([
            'tenant_id' => $this->school->id,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $staff->assignRole('school_staff');

        $this->actingAs($staff)
            ->get(route('school.question-papers.index', ['tenantId' => $this->school->id]))
            ->assertForbidden();
    }

    /** @return array{0: User, 1: Teacher} */
    private function makeTeacher(string $email): array
    {
        $user = User::factory()->create([
            'tenant_id' => $this->school->id,
            'email' => $email,
            'must_change_password' => false,
        ]);
        $user->assignRole('teacher');

        $teacher = Teacher::create([
            'tenant_id' => $this->school->id,
            'user_id' => $user->id,
            'name' => Str::headline(Str::before($email, '@')),
            'email' => $email,
            'subject_ids' => [$this->subject->id],
            'subject' => $this->subject->label,
            'status' => 'active',
        ]);

        return [$user, $teacher];
    }
}
