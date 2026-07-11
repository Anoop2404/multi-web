<?php

namespace Tests\Unit\Services\Training;

use App\Models\FeeReceipt;
use App\Models\SahodayaProfile;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSchoolFee;
use App\Models\User;
use App\Services\Training\TrainingSchoolFeeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrainingSchoolFeeServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: Tenant, 2: TrainingProgram, 3: User} */
    private function seedSchoolFeeProgram(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sahodaya',
            'type' => 'sahodaya',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'TS',
            'receipt_next_number' => 1,
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test School',
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'is_active' => true,
            'membership_status' => 'approved',
        ]);
        $program = TrainingProgram::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Batch Fee Workshop',
            'status' => 'published',
            'fee_type' => 'school',
            'fee_amount' => 250,
            'registration_open' => now()->subDay()->toDateString(),
            'registration_close' => now()->addDays(10)->toDateString(),
        ]);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        Role::findByName('sahodaya_admin', 'web');
        $admin->assignRole('sahodaya_admin');

        return [$sahodaya, $school, $program, $admin];
    }

    public function test_sync_counts_teachers_and_computes_total_due(): void
    {
        [, $school, $program] = $this->seedSchoolFeeProgram();

        foreach (['A', 'B', 'C'] as $i => $name) {
            $teacher = Teacher::create([
                'tenant_id' => $school->id,
                'name' => "Teacher {$name}",
                'email' => "t{$i}@school.test",
                'status' => 'active',
            ]);
            TrainingRegistration::create([
                'program_id' => $program->id,
                'teacher_id' => $teacher->id,
                'school_id' => $school->id,
                'status' => 'registered',
                'registration_source' => 'school',
            ]);
        }

        $fee = app(TrainingSchoolFeeService::class)->syncForSchool($program, $school);

        $this->assertSame(3, (int) $fee->teacher_count);
        $this->assertEquals(750.0, (float) $fee->total_due);
        $this->assertSame('pending', $fee->status);

        $regs = TrainingRegistration::where('program_id', $program->id)->get();
        foreach ($regs as $reg) {
            $this->assertSame('auto_approved', $reg->fee_status);
            $this->assertSame(0.0, $reg->feeTotalDue());
        }
    }

    public function test_approve_confirms_registrations_and_marks_school_fee_paid(): void
    {
        [, $school, $program, $admin] = $this->seedSchoolFeeProgram();

        $teacher = Teacher::create([
            'tenant_id' => $school->id,
            'name' => 'Teacher One',
            'email' => 'one@school.test',
            'status' => 'active',
        ]);
        $registration = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id' => $school->id,
            'status' => 'registered',
            'registration_source' => 'school',
            'fee_status' => 'auto_approved',
        ]);

        $service = app(TrainingSchoolFeeService::class);
        $schoolFee = $service->syncForSchool($program, $school);

        $receipt = FeeReceipt::create([
            'feeable_type' => TrainingSchoolFee::class,
            'feeable_id' => $schoolFee->id,
            'file_path' => 'training-payments/test/proof.png',
            'amount' => 250,
            'status' => 'uploaded',
            'payment_date' => now()->toDateString(),
        ]);
        $schoolFee->update(['fee_receipt_id' => $receipt->id, 'status' => 'proof_uploaded']);

        $confirmed = $service->approve($schoolFee->fresh(), $admin->id);

        $this->assertSame(1, $confirmed);
        $this->assertSame('approved', $schoolFee->fresh()->status);
        $this->assertSame('approved', $receipt->fresh()->status);
        $this->assertSame('confirmed', $registration->fresh()->status);
        $this->assertSame('approved', $registration->fresh()->fee_status);
        $this->assertNotNull($receipt->fresh()->receipt_number);
    }

    public function test_flat_fee_program_does_not_bill_school_batch(): void
    {
        [, $school] = $this->seedSchoolFeeProgram();
        $program = TrainingProgram::create([
            'tenant_id' => $school->parent_id,
            'title' => 'Flat Fee Workshop',
            'status' => 'published',
            'fee_type' => 'flat',
            'fee_amount' => 100,
        ]);
        $teacher = Teacher::create([
            'tenant_id' => $school->id,
            'name' => 'Flat Teacher',
            'email' => 'flat@school.test',
            'status' => 'active',
        ]);
        TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id' => $school->id,
            'status' => 'registered',
            'registration_source' => 'school',
        ]);

        $fee = app(TrainingSchoolFeeService::class)->syncForSchool($program, $school);

        $this->assertSame(1, (int) $fee->teacher_count);
        $this->assertEquals(0.0, (float) $fee->total_due);
        $this->assertSame('waived', $fee->status);
    }
}
