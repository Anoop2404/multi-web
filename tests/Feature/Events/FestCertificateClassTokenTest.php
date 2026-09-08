<?php

namespace Tests\Feature\Events;

use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestCertificateService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The {class} token (a certificate template placeholder for the recipient's class,
 * e.g. "of class VIII") already existed for training/topper certificates but not fest
 * ones -- FestCertificateService::resolveFieldValues() never resolved a 'class' entry
 * at all, so a fest template referencing {class} would render the literal token text.
 */
class FestCertificateClassTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_winner_certificate_resolves_the_students_class_name(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Class Token Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CT', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Class Token School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'VIII', 'class_number' => 8]);
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Class Token Student',
            'status' => 'active', 'verification_status' => 'verified', 'eligible_kalolsav' => true,
        ]);

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Class Token Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'CT1']);

        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => $student->id,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);

        $certificate = Certificate::create([
            'entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
            'cert_type' => 'winner', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(),
        ]);

        $context = app(FestCertificateService::class)->renderContext($certificate);

        $this->assertSame('VIII', $context['fieldValues']['class']);
    }

    public function test_class_is_blank_rather_than_erroring_when_the_participant_has_no_student_row(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Class Token Sahodaya B',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CTB', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Class Token School B', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Class Token Event B', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'CTB1']);

        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved',
        ]);
        // No real Student row behind this id -- counts_only-mode fixture, same shape as
        // FestCertificateEffectiveGradeTest's participants.
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => 999999,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);

        $certificate = Certificate::create([
            'entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
            'cert_type' => 'winner', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(),
        ]);

        $context = app(FestCertificateService::class)->renderContext($certificate);

        $this->assertSame('', $context['fieldValues']['class']);
    }
}
