<?php

namespace Tests\Feature;

use App\Models\ClassCategory;
use App\Models\MembershipFeeSlab;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\SchoolYearSubmission;
use App\Models\Tenant;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Membership\RegistrationStatusService;
use App\Support\AcademicYear;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MembershipRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function cluster(): array
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'KNR Sahodaya',
            'domain'    => 'knr-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'                   => $sahodaya->id,
            'prefix'                      => 'KNR',
            'student_data_mode'           => 'counts_only',
            'membership_fee_type'         => 'variable_by_student_count',
            'teacher_registration_enabled'=> true,
        ]);

        MembershipFeeSlab::create([
            'sahodaya_id'   => $sahodaya->id,
            'academic_year' => AcademicYear::current(),
            'min_students'  => 0,
            'max_students'  => 500,
            'amount'        => 5000,
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Govt HS',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'GHS',
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        return compact('sahodaya', 'school');
    }

    public function test_effective_master_data_hides_global_categories(): void
    {
        ['sahodaya' => $sahodaya] = $this->cluster();
        $global = ClassCategory::global()->first();

        \App\Models\ClassCategoryOverride::create([
            'sahodaya_id'       => $sahodaya->id,
            'class_category_id' => $global->id,
            'is_hidden'         => true,
        ]);

        $resolver = app(EffectiveMasterDataResolver::class);
        $effective = $resolver->classCategories($sahodaya->id);

        $this->assertFalse($effective->contains('id', $global->id));
        $this->assertGreaterThan(0, $effective->count());
    }

    public function test_membership_number_format(): void
    {
        ['school' => $school] = $this->cluster();

        $registration = app(RegistrationStatusService::class)->beginAnnualRegistration($school);

        $this->assertMatchesRegularExpression('/^KNR\/GHS\/\d{4}$/', $registration->reg_no);
    }

    public function test_begin_registration_creates_submission_tracks(): void
    {
        ['school' => $school] = $this->cluster();

        $registration = app(RegistrationStatusService::class)->beginAnnualRegistration($school);

        $this->assertDatabaseHas('registrations', ['id' => $registration->id, 'school_id' => $school->id]);
        $this->assertNotNull($registration->reg_no);
        $submission = SchoolYearSubmission::find($registration->school_year_submission_id);
        $this->assertSame('not_applicable', $submission->full_records_status);
        $this->assertSame('pending', $submission->counts_status);
        $this->assertSame('pending', $submission->teacher_status);
    }

    public function test_begin_registration_reuses_existing_submission(): void
    {
        ['school' => $school] = $this->cluster();
        $year = AcademicYear::forSchool($school);

        SchoolYearSubmission::create([
            'school_id'           => $school->id,
            'academic_year'       => $year,
            'full_records_status' => 'not_applicable',
            'counts_status'       => 'pending',
            'teacher_status'      => 'pending',
        ]);

        $registration = app(RegistrationStatusService::class)->beginAnnualRegistration($school);

        $this->assertDatabaseHas('registrations', [
            'id'            => $registration->id,
            'school_id'     => $school->id,
            'academic_year' => $year,
        ]);
        $this->assertSame(1, SchoolYearSubmission::where('school_id', $school->id)->where('academic_year', $year)->count());
    }

    public function test_begin_registration_is_idempotent(): void
    {
        ['school' => $school] = $this->cluster();

        $first = app(RegistrationStatusService::class)->beginAnnualRegistration($school);
        $second = app(RegistrationStatusService::class)->beginAnnualRegistration($school);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SchoolYearSubmission::where('school_id', $school->id)->count());
    }

    public function test_payment_advance_after_data_approved(): void
    {
        ['school' => $school] = $this->cluster();

        $registration = app(RegistrationStatusService::class)->beginAnnualRegistration($school);
        $submission = $registration->submission;

        $submission->update([
            'counts_status'  => 'approved',
            'teacher_status' => 'approved',
        ]);

        app(RegistrationStatusService::class)->checkAndAdvanceToPayment($registration->fresh());

        $registration = $registration->fresh();
        $this->assertSame('payment_pending', $registration->registration_status);
        $this->assertSame('5000.00', $registration->membership_fee_amount);
    }

    public function test_payment_submitted_notifies_sahodaya(): void
    {
        \Illuminate\Support\Facades\Event::fake([\Illuminate\Mail\Events\MessageSending::class]);

        ['sahodaya' => $sahodaya, 'school' => $school] = $this->cluster();

        \App\Models\User::create([
            'tenant_id' => $sahodaya->id,
            'name'      => 'Sahodaya Admin',
            'email'     => 'admin@knr-sahodaya.test',
            'password'  => bcrypt('password'),
        ]);

        app(\App\Services\Membership\MembershipNotifier::class)->paymentSubmitted(
            $school,
            AcademicYear::current(),
            5000.00,
            'TXN123',
            'UPI',
        );

        \Illuminate\Support\Facades\Event::assertDispatched(\Illuminate\Mail\Events\MessageSending::class, function ($event) use ($school) {
            $message = $event->message;

            return in_array('admin@knr-sahodaya.test', array_map(fn ($a) => $a->getAddress(), $message->getTo()), true)
                && str_contains($message->getSubject(), $school->name)
                && str_contains($message->getBody()->bodyToString(), 'TXN123')
                && str_contains($message->getBody()->bodyToString(), '/membership/payments');
        });
    }

    public function test_sahodaya_can_update_payment_details(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        ['sahodaya' => $sahodaya] = $this->cluster();

        $admin = \App\Models\User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $response = $this->actingAs($admin)->put("/sahodaya-admin/{$sahodaya->id}/membership/payment-details", [
            'payment_bank_name'    => 'Federal Bank',
            'payment_account_no'   => '9876543210',
            'payment_ifsc'         => 'FDRL0001234',
            'payment_upi'          => 'knr-sahodaya@upi',
            'payment_instructions' => 'Mention school code in remarks.',
        ]);

        $response->assertRedirect();

        $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->first();
        $this->assertSame('Federal Bank', $profile->payment_bank_name);
        $this->assertSame('9876543210', $profile->payment_account_no);
        $this->assertStringContainsString('UPI: knr-sahodaya@upi', $profile->paymentDetailsText());
    }

    public function test_submission_student_image_upload_stores_on_s3(): void
    {
        Storage::fake('s3');
        Storage::fake('shared');
        config(['filesystems.upload_disk' => 'shared']);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        ['school' => $school] = $this->cluster();

        SahodayaProfile::where('tenant_id', $school->parent_id)->update([
            'student_data_mode' => 'full_records',
        ]);

        app(\App\Services\Students\SchoolClassProvisioner::class)->ensureForSchool($school);
        $class = \App\Models\SchoolClass::where('tenant_id', $school->id)->firstOrFail();

        $admin = \App\Models\User::factory()->create([
            'tenant_id'         => $school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        app(RegistrationStatusService::class)->beginAnnualRegistration($school->fresh());

        $file = UploadedFile::fake()->image('submission-student.jpg');

        $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/registration/students", [
                'name'            => 'Submission Student',
                'school_class_id' => $class->id,
                'image'           => $file,
            ])
            ->assertRedirect();

        $student = \App\Models\SubmissionStudent::firstOrFail();
        $this->assertNotNull($student->image_path);
        Storage::disk('s3')->assertExists($student->image_path);

        $this->actingAs($admin)
            ->get("/school-admin/{$school->id}/registration/students/{$student->id}/image")
            ->assertOk();
    }
}
