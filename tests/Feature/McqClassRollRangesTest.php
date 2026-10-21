<?php

namespace Tests\Feature;

use App\Models\{Tenant, McqExam, McqRegistration, SchoolClass, Student};
use App\Services\Mcq\McqHallTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class McqClassRollRangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_and_apply_preserve_approvals_and_future_class_sequences(): void
    {
        Storage::fake('local');
        $tenant = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Range test']);
        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'School', 'parent_id' => $tenant->id]);
        $exam = McqExam::create(['tenant_id' => $tenant->id, 'title' => 'STSE', 'status' => 'published', 'next_hall_ticket_no' => 1]);
        $rows = [];
        foreach (['LKG', 'UKG', 'Class 1', 'Class 10'] as $name) {
            $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => $name, 'is_active' => true]);
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Student', 'status' => 'active']);
            $rows[] = McqRegistration::create(['exam_id' => $exam->id, 'school_id' => $school->id, 'student_id' => $student->id, 'hall_ticket_no' => '999', 'status' => 'registered', 'approval_status' => 'pending_payment']);
        }
        $args = ['tenant' => $tenant->id, 'exam' => $exam->id];
        $this->artisan('mcq:set-class-roll-ranges', $args)->assertSuccessful();
        $this->assertSame('999', $rows[0]->fresh()->hall_ticket_no);
        $this->artisan('mcq:set-class-roll-ranges', $args + ['--apply' => true])->assertSuccessful();
        foreach ([10000, 11000, 12000, 21000] as $index => $start) {
            $this->assertSame((string) $start, $rows[$index]->fresh()->hall_ticket_no);
            $this->assertSame('pending_payment', $rows[$index]->fresh()->approval_status);
        }
        $this->assertCount(1, Storage::disk('local')->allFiles('mcq-roll-backups'));
        $next = new \ReflectionMethod(McqHallTicketService::class, 'nextNumberForClass');
        $this->assertSame(11001, $next->invoke(app(McqHallTicketService::class), $exam->fresh(), $rows[1]));
    }
}
