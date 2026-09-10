<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUserCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Overview page's "Share & QR" modal (EventShareModal.vue) lets an admin hand a
 * visitor a link/QR to the event's public page — these two downloads are its backend.
 */
class FestEventShareControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(FestEvent $event, Tenant $sahodaya): User
    {
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('event_admin');
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('event_admin'));
        // event_admin is scoped per-event via FestEventStaff — see EnsureSahodayaAdmin.
        FestEventStaff::create(['event_id' => $event->id, 'user_id' => $admin->id, 'duty' => 'event_admin']);

        return $admin;
    }

    private function makeSahodayaAndEvent(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Share QR Sahodaya',
            'domain' => 'share-qr-sahodaya.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'SQ', 'student_data_mode' => 'counts_only']);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Share & QR Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open', 'venue' => 'Community Hall',
            'event_start' => '2026-11-10', 'event_end' => '2026-11-12',
        ]);

        return [$sahodaya, $event];
    }

    public function test_qr_image_download_returns_a_branded_png(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$sahodaya, $event] = $this->makeSahodayaAndEvent();
        $admin = $this->makeAdmin($event, $sahodaya);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.share.qr-image', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('share-qr-kalotsav-qr.png', $response->headers->get('Content-Disposition'));
        // PNG magic bytes.
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $response->getContent());
    }

    public function test_qr_pdf_download_returns_a_pdf(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$sahodaya, $event] = $this->makeSahodayaAndEvent();
        $admin = $this->makeAdmin($event, $sahodaya);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.share.qr-pdf', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_share_downloads_are_forbidden_for_a_different_sahodayas_admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$sahodaya, $event] = $this->makeSahodayaAndEvent();

        $otherSahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Other Sahodaya',
            'domain' => 'other-sahodaya-share.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $otherSahodaya->id, 'prefix' => 'OS', 'student_data_mode' => 'counts_only']);
        // A broad (non-event-scoped) role, so this hits FestEventShareController's own
        // cross-tenant guard rather than EnsureSahodayaAdmin's per-event staff scoping.
        $otherAdmin = User::factory()->create(['tenant_id' => $otherSahodaya->id, 'email_verified_at' => now()]);
        $otherAdmin->assignRole('sahodaya_admin');

        $this->actingAs($otherAdmin)->get(route('sahodaya.events.share.qr-image', [
            'tenantId' => $otherSahodaya->id, 'event' => $event->id,
        ]))->assertForbidden();
    }
}
