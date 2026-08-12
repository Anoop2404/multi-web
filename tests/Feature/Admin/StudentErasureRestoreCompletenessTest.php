<?php

namespace Tests\Feature\Admin;

use App\Models\McqAttendanceCorrectionRequest;
use App\Models\McqCertificate;
use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentErasureBatch;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression test for WF-05 (functional audit, 2026-08-11/12): erasing a
 * student hard-deletes its mcq_registrations row, which cascade-deletes
 * mcq_marks, mcq_certificates, and mcq_attendance_correction_requests at the
 * database level. None of the three were previously captured in the erasure
 * snapshot, so "restoring" an erasure batch brought the student and their MCQ
 * registration back but permanently lost their scored mark, certificate, and
 * attendance-correction history — despite the admin UI promising the action
 * is reversible. This proves a full round trip preserves all three.
 */
class StudentErasureRestoreCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_restoring_an_erasure_batch_recovers_mcq_marks_certificates_and_corrections(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['tenancy.database_per_sahodaya' => false]);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Erasure Test Sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Erasure Test School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $schoolClass = SchoolClass::create([
            'tenant_id' => $school->id, 'name' => '10', 'display_order' => 1, 'is_active' => true,
        ]);

        $student = Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $schoolClass->id,
            'admission_number' => 'ADM-001',
            'name' => 'Erasure Sentinel Student',
            'status' => 'active',
        ]);

        $exam = McqExam::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Sentinel Exam',
            'exam_type' => 'assessment',
            'conductor_level' => 'sahodaya',
            'status' => 'completed',
        ]);

        $registration = McqRegistration::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'school_id' => $school->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $mark = McqMark::create([
            'registration_id' => $registration->id,
            'correct_count' => 18,
            'wrong_count' => 2,
            'unanswered_count' => 0,
            'score' => 18,
            'percentage' => 90,
            'grade' => 'A',
        ]);

        $certificate = McqCertificate::create([
            'registration_id' => $registration->id,
            'verification_uuid' => (string) Str::uuid(),
            'generated_at' => now(),
        ]);

        $correction = McqAttendanceCorrectionRequest::create([
            'tenant_id' => $sahodaya->id,
            'exam_id' => $exam->id,
            'registration_id' => $registration->id,
            'previous_status' => 'absent',
            'requested_status' => 'submitted',
            'requested_by' => 1,
            'status' => 'approved',
        ]);

        $superadmin = User::factory()->create(['tenant_id' => null, 'email_verified_at' => now()]);
        $superadmin->assignRole('superadmin');

        // Erase.
        $this->actingAs($superadmin)
            ->delete("/admin/tenants/{$school->id}/erase-students", [
                'confirm_school_name' => $school->name,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('mcq_registrations', ['id' => $registration->id]);
        $this->assertDatabaseMissing('mcq_marks', ['id' => $mark->id]);
        $this->assertDatabaseMissing('mcq_certificates', ['id' => $certificate->id]);
        $this->assertDatabaseMissing('mcq_attendance_correction_requests', ['id' => $correction->id]);

        $batch = StudentErasureBatch::where('school_id', $school->id)->firstOrFail();
        $this->assertNotEmpty($batch->snapshot['mcq_marks'] ?? []);
        $this->assertNotEmpty($batch->snapshot['mcq_certificates'] ?? []);
        $this->assertNotEmpty($batch->snapshot['mcq_attendance_correction_requests'] ?? []);

        // Restore.
        $this->actingAs($superadmin)
            ->post("/admin/tenants/{$school->id}/erasure-batches/{$batch->id}/restore")
            ->assertRedirect();

        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertDatabaseHas('mcq_registrations', ['id' => $registration->id]);

        $this->assertDatabaseHas('mcq_marks', [
            'id' => $mark->id,
            'registration_id' => $registration->id,
            'correct_count' => 18,
            'grade' => 'A',
        ]);
        $this->assertDatabaseHas('mcq_certificates', [
            'id' => $certificate->id,
            'registration_id' => $registration->id,
            'verification_uuid' => $certificate->verification_uuid,
        ]);
        $this->assertDatabaseHas('mcq_attendance_correction_requests', [
            'id' => $correction->id,
            'registration_id' => $registration->id,
            'status' => 'approved',
        ]);
    }
}
