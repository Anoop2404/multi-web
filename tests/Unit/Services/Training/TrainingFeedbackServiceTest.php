<?php

namespace Tests\Unit\Services\Training;

use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingFeedback;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Services\Training\TrainingFeedbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TrainingFeedbackServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: Tenant, 2: Teacher, 3: TrainingProgram, 4: TrainingRegistration} */
    private function seedConfirmedRegistration(): array
    {
        $sahodayaId = (string) Str::uuid();
        $schoolId = (string) Str::uuid();

        $sahodaya = Tenant::create([
            'id' => $sahodayaId,
            'name' => 'Feedback Sahodaya',
            'type' => 'sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id' => $schoolId,
            'name' => 'Feedback School',
            'type' => 'school',
            'parent_id' => $sahodayaId,
            'is_active' => true,
        ]);

        $teacher = Teacher::create([
            'tenant_id' => $school->id,
            'name' => 'Teacher A',
            'status' => 'active',
        ]);

        $program = TrainingProgram::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Feedback Programme',
            'status' => 'completed',
            'fee_type' => 'none',
        ]);

        $registration = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id' => $school->id,
            'status' => 'confirmed',
        ]);

        return [$sahodaya, $school, $teacher, $program, $registration];
    }

    public function test_submit_creates_feedback(): void
    {
        [, , , , $registration] = $this->seedConfirmedRegistration();
        $service = app(TrainingFeedbackService::class);

        $feedback = $service->submit($registration, [
            'rating' => 4,
            'comments' => 'Good session',
            'content_rating' => 5,
            'trainer_rating' => 4,
            'venue_rating' => 3,
        ]);

        $this->assertInstanceOf(TrainingFeedback::class, $feedback);
        $this->assertSame(4, $feedback->rating);
        $this->assertSame('submitted', $feedback->status);
        $this->assertSame($registration->id, $feedback->registration_id);
    }

    public function test_submit_rejects_duplicate(): void
    {
        [, , , , $registration] = $this->seedConfirmedRegistration();
        $service = app(TrainingFeedbackService::class);

        $service->submit($registration, ['rating' => 5]);

        $this->expectException(ValidationException::class);
        $service->submit($registration->fresh(['feedback']), ['rating' => 3]);
    }

    public function test_submit_rejects_unconfirmed(): void
    {
        [, , , , $registration] = $this->seedConfirmedRegistration();
        $registration->update(['status' => 'registered']);
        $service = app(TrainingFeedbackService::class);

        $this->expectException(ValidationException::class);
        $service->submit($registration->fresh(), ['rating' => 4]);
    }

    public function test_mark_reviewed(): void
    {
        [, , , , $registration] = $this->seedConfirmedRegistration();
        $service = app(TrainingFeedbackService::class);
        $feedback = $service->submit($registration, ['rating' => 5]);

        $service->markReviewed($feedback, 42);

        $feedback->refresh();
        $this->assertSame('reviewed', $feedback->status);
        $this->assertSame(42, $feedback->reviewed_by_user_id);
        $this->assertNotNull($feedback->reviewed_at);
    }
}
