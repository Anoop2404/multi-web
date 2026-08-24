<?php

namespace Tests\Feature\Events;

use App\Models\CertificateTemplate;
use App\Models\FestEvent;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FestCertificateService::resolveTemplate() picks between templates purely by
 * is_active + recency — two active templates for the exact same (tenant, event_type,
 * event_id, item_id, certificate_type) tuple is an ambiguous, silently-wrong state, not
 * just an untidy one (this is what let a stale template win over a deliberately-updated
 * one in practice). CertificateTemplateController::store()/update() already auto-
 * deactivate the exact-scope sibling when saving a new active template — this covers
 * the part that was still missing: telling the admin it happened, rather than doing it
 * silently.
 */
class CertificateTemplateDeactivationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User, event: FestEvent} */
    private function makeSahodayaAdminAndEvent(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Template Lock Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'TL', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Template Lock Event', 'event_type' => 'kalolsavam']);

        return compact('sahodaya', 'admin', 'event');
    }

    public function test_saving_a_new_active_template_deactivates_the_existing_one_and_reports_it(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAdminAndEvent();

        $existing = CertificateTemplate::create([
            'tenant_id' => $sahodaya->id, 'event_type' => 'fest', 'event_id' => $event->id,
            'certificate_type' => 'winner', 'title' => 'Old Merit Template', 'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('sahodaya.certificate-templates.store', [
            'tenantId' => $sahodaya->id,
        ]), [
            'event_type'       => 'fest',
            'event_id'         => $event->id,
            'certificate_type' => 'winner',
            'title'            => 'New Merit Template',
            'is_active'        => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', function ($message) {
            return str_contains($message, '1 existing template(s)')
                && str_contains($message, 'automatically deactivated');
        });

        $this->assertFalse($existing->fresh()->is_active, 'The old template should have been deactivated.');
        $newTemplate = CertificateTemplate::where('title', 'New Merit Template')->firstOrFail();
        $this->assertTrue($newTemplate->is_active);
    }

    public function test_activating_via_update_deactivates_siblings_and_reports_it(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAdminAndEvent();

        $active = CertificateTemplate::create([
            'tenant_id' => $sahodaya->id, 'event_type' => 'fest', 'event_id' => $event->id,
            'certificate_type' => 'participation', 'title' => 'Currently Active', 'is_active' => true,
        ]);
        $draft = CertificateTemplate::create([
            'tenant_id' => $sahodaya->id, 'event_type' => 'fest', 'event_id' => $event->id,
            'certificate_type' => 'participation', 'title' => 'Draft', 'is_active' => false,
        ]);

        $response = $this->actingAs($admin)->put(route('sahodaya.certificate-templates.update', [
            'tenantId' => $sahodaya->id, 'template' => $draft->id,
        ]), ['is_active' => true]);

        $response->assertRedirect();
        $response->assertSessionHas('success', function ($message) {
            return str_contains($message, '1 existing template(s)')
                && str_contains($message, 'automatically deactivated');
        });

        $this->assertFalse($active->fresh()->is_active);
        $this->assertTrue($draft->fresh()->is_active);
    }
}
