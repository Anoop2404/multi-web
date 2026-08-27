<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateIndex;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingAttendance;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Support\TenancyDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for PublicCertificateController::resolveCertificateOwner() — the
 * fix for certificates.verify/print/pdf always running against the central DB connection
 * (no certificates table there) regardless of which domain a request arrived on, since
 * the only tenancy middleware on these routes resolves a tenant from a route parameter
 * these uuid-keyed routes don't have.
 *
 * This suite runs with TENANCY_DATABASE_PER_SAHODAYA=false (phpunit.xml default), so every
 * tenant shares one physical database — there is no way, in this harness, to prove
 * "resolves the right tenant's *separate* database regardless of request host" the way
 * production actually breaks. What these tests verify instead, and what's genuinely
 * meaningful in this environment: the uuid-driven resolution mechanism itself works
 * (scan-fallback finds an unindexed certificate, self-heals CertificateIndex on a hit,
 * the previously-unwrapped Training branch now runs inside TenancyDatabase::withTenantDatabase()
 * without error) and the write-side observers populate the index correctly. Real
 * cross-database isolation is exercised by TenancyDatabaseCurrentTenantTest (unit-level,
 * simulated connection swap) and, ultimately, by hitting the real production URL after
 * deploy per the plan's verification section.
 */
class PublicCertificateTenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodaya(string $name): Tenant
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => $name,
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'TR', 'student_data_mode' => 'counts_only']);

        return $sahodaya;
    }

    public function test_verify_finds_a_fest_certificate_via_scan_fallback_and_self_heals_the_index(): void
    {
        $sahodaya = $this->makeSahodaya('Resolution Test Sahodaya A');
        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Resolution Test School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Resolution Test Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => 501,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);
        $certificate = Certificate::create([
            'entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
            'cert_type' => 'participation', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(),
        ]);

        // The observer only fires with real tenant context active — this certificate was
        // created plainly above (matching how a genuinely pre-index, pre-this-fix
        // certificate would look), so the index must start empty.
        $this->assertFalse(CertificateIndex::where('verification_uuid', $certificate->verification_uuid)->exists());

        $response = $this->get(route('certificates.verify', $certificate->verification_uuid));

        $response->assertOk();
        $response->assertViewIs('fest.certificate-verify');

        $this->assertTrue(
            CertificateIndex::where('verification_uuid', $certificate->verification_uuid)
                ->where('tenant_id', $sahodaya->id)
                ->where('source_table', CertificateIndex::SOURCE_CERTIFICATE)
                ->exists(),
            'A scan-fallback hit must self-heal the index so the next lookup is O(1).'
        );
    }

    public function test_verify_resolves_a_training_certificate_through_the_newly_wrapped_branch(): void
    {
        $sahodaya = $this->makeSahodaya('Resolution Test Sahodaya B');
        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Resolution Test School B', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $program = TrainingProgram::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Resolution Test Program',
            'venue' => 'Test Venue', 'status' => 'completed', 'fee_type' => 'none',
        ]);
        $session = TrainingSession::create(['program_id' => $program->id, 'title' => 'Day 1']);
        $teacher = Teacher::create(['tenant_id' => $school->id, 'name' => 'Resolution Teacher', 'status' => 'active']);
        $registration = TrainingRegistration::create([
            'program_id' => $program->id, 'teacher_id' => $teacher->id, 'school_id' => $school->id, 'status' => 'confirmed',
        ]);
        TrainingAttendance::create(['session_id' => $session->id, 'registration_id' => $registration->id, 'status' => 'present']);

        $certificate = Certificate::create([
            'entity_type' => TrainingRegistration::class, 'entity_id' => $registration->id,
            'cert_type' => 'participation', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(),
        ]);

        // Before this fix, verify()'s Training branch ran with zero TenancyDatabase
        // wrapping at all — this assertion is the direct regression guard for that.
        $response = $this->get(route('certificates.verify', $certificate->verification_uuid));

        $response->assertOk();
        $response->assertViewIs('training.certificate-verify');
    }

    public function test_certificate_observer_indexes_a_newly_created_certificate(): void
    {
        $sahodaya = $this->makeSahodaya('Resolution Test Sahodaya C');

        TenancyDatabase::initializeForTenant($sahodaya);

        $certificate = Certificate::create([
            'entity_type' => TrainingRegistration::class, 'entity_id' => 999,
            'cert_type' => 'participation', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(),
        ]);

        // Leave tenancy before the test client makes its own request below — a leftover
        // initialized tenant would otherwise leak into that request's own context.
        tenancy()->end();

        $this->assertTrue(
            CertificateIndex::where('verification_uuid', $certificate->verification_uuid)
                ->where('tenant_id', $sahodaya->id)
                ->where('source_table', CertificateIndex::SOURCE_CERTIFICATE)
                ->exists()
        );
    }
}
