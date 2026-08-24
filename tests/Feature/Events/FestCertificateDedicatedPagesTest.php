<?php

namespace Tests\Feature\Events;

use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Merit and Participation certificate pages are dedicated routes/components
 * (FestCertificateController::meritCertificates()/participationCertificatesPage()),
 * not tabs on the combined Certificates page — each must only ever receive its own
 * cert_type, never a mix.
 */
class FestCertificateDedicatedPagesTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodaya(): Tenant
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Dedicated Pages Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'DP', 'student_data_mode' => 'counts_only']);

        return $sahodaya;
    }

    private function makeSchool(string $sahodayaId): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodayaId,
            'name' => 'Dedicated Pages School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);
    }

    private function makeCertificate(FestEvent $event, FestEventItem $item, string $schoolId, string $certType): Certificate
    {
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schoolId, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => rand(1000, 999999),
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        return Certificate::create([
            'entity_type'        => FestParticipant::class,
            'entity_id'          => $participant->id,
            'cert_type'          => $certType,
            'verification_uuid' => (string) Str::uuid(),
            'generated_at'      => now(),
        ]);
    }

    public function test_merit_page_only_shows_winner_certificates(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Dedicated Merit Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);

        $this->makeCertificate($event, $item, $school->id, 'winner');
        $this->makeCertificate($event, $item, $school->id, 'winner');
        $this->makeCertificate($event, $item, $school->id, 'participation');

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.merit', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('certificates', 2)
            ->where('certificates.0.cert_type', 'winner')
            ->where('certificates.1.cert_type', 'winner')
        );
    }

    public function test_participants_page_only_shows_participation_certificates(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Dedicated Participation Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);

        $this->makeCertificate($event, $item, $school->id, 'participation');
        $this->makeCertificate($event, $item, $school->id, 'winner');

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.participants', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('certificates', 1)
            ->where('certificates.0.cert_type', 'participation')
        );
    }

    public function test_merit_page_bulk_render_can_be_scoped_to_a_single_item(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Scoped Render Event', 'event_type' => 'kalolsavam']);
        $itemA = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);
        $itemB = FestEventItem::create(['event_id' => $event->id, 'title' => 'Group Dance', 'item_code' => 'GD1']);

        $this->makeCertificate($event, $itemA, $school->id, 'winner');
        $this->makeCertificate($event, $itemB, $school->id, 'winner');

        $response = $this->actingAs($admin)->post(route('sahodaya.events.certificates.batches.store', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['cert_type' => 'winner', 'item_id' => $itemA->id]);

        $response->assertRedirect();
        $batchId = session('certificate_batch_id');
        $this->assertNotNull($batchId);

        $batch = \App\Models\CertificateBatch::findOrFail($batchId);
        $this->assertSame(1, $batch->total_count, 'Only item A\'s winner certificate should be in scope.');
        $this->assertSame($itemA->id, $batch->item_id);
    }
}
