<?php

namespace Tests\Unit\Services\Training;

use App\Models\NotificationTemplate;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\User;
use App\Services\Training\TrainingWaitlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingWaitlistServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: Tenant, 2: TrainingProgram} */
    private function seedProgram(int $max = 1): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sahodaya',
            'type' => 'sahodaya',
            'is_active' => true,
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
            'title' => 'Waitlist Workshop',
            'status' => 'published',
            'fee_type' => 'none',
            'fee_amount' => 0,
            'max_participants' => $max,
            'registration_open' => now()->subDay()->toDateString(),
            'registration_close' => now()->addDays(10)->toDateString(),
        ]);

        NotificationTemplate::updateOrCreate(
            ['slug' => 'training.waitlist.promoted'],
            [
                'title' => 'Promoted',
                'body_template' => '{{program_title}} {{teacher_name}} {{status}}',
                'is_active' => true,
                'channels_json' => ['in_app'],
            ]
        );

        return [$sahodaya, $school, $program];
    }

    private function makeTeacher(Tenant $school, string $name): Teacher
    {
        $user = User::factory()->create(['tenant_id' => $school->id]);

        return Teacher::create([
            'tenant_id' => $school->id,
            'user_id' => $user->id,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@school.test',
            'status' => 'active',
        ]);
    }

    public function test_full_program_enqueues_with_position(): void
    {
        [, $school, $program] = $this->seedProgram(1);
        $waitlist = app(TrainingWaitlistService::class);

        $t1 = $this->makeTeacher($school, 'Alice');
        $t2 = $this->makeTeacher($school, 'Bob');

        $first = TrainingRegistration::create(array_merge([
            'program_id' => $program->id,
            'teacher_id' => $t1->id,
            'school_id' => $school->id,
            'registration_source' => 'school',
        ], $waitlist->resolveCreateAttributes($program, 'school')));

        $second = TrainingRegistration::create(array_merge([
            'program_id' => $program->id,
            'teacher_id' => $t2->id,
            'school_id' => $school->id,
            'registration_source' => 'school',
        ], $waitlist->resolveCreateAttributes($program, 'school')));

        $this->assertSame('confirmed', $first->status);
        $this->assertNull($first->waitlist_position);
        $this->assertSame('waitlisted', $second->status);
        $this->assertSame(1, (int) $second->waitlist_position);
    }

    public function test_cancel_promotes_first_waitlisted(): void
    {
        [, $school, $program] = $this->seedProgram(1);
        $waitlist = app(TrainingWaitlistService::class);

        $t1 = $this->makeTeacher($school, 'Alice');
        $t2 = $this->makeTeacher($school, 'Bob');

        $seated = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $t1->id,
            'school_id' => $school->id,
            'status' => 'confirmed',
            'registration_source' => 'school',
        ]);
        $waiting = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $t2->id,
            'school_id' => $school->id,
            'status' => 'waitlisted',
            'waitlist_position' => 1,
            'registration_source' => 'school',
        ]);

        $waitlist->cancelAndPromote($seated);

        $this->assertSame('cancelled', $seated->fresh()->status);
        $this->assertSame('confirmed', $waiting->fresh()->status);
        $this->assertNull($waiting->fresh()->waitlist_position);
    }
}
