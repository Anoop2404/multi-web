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

    public function test_also_apply_to_event_ids_creates_an_independent_copy_per_event(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $eventOne] = $this->makeSahodayaAdminAndEvent();
        $eventTwo = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Second Event', 'event_type' => 'kalolsavam']);
        $eventThree = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Third Event', 'event_type' => 'kalolsavam']);

        $response = $this->actingAs($admin)->post(route('sahodaya.certificate-templates.store', [
            'tenantId' => $sahodaya->id,
        ]), [
            'event_type'              => 'fest',
            'event_id'                => $eventOne->id,
            'certificate_type'        => 'participation',
            'title'                   => 'Shared Design',
            'is_active'               => true,
            'also_apply_to_event_ids' => [$eventTwo->id, $eventThree->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', fn ($message) => str_contains($message, 'applied to 3 events'));

        $templates = CertificateTemplate::where('tenant_id', $sahodaya->id)
            ->where('certificate_type', 'participation')
            ->where('title', 'Shared Design')
            ->get();

        $this->assertCount(3, $templates);
        $this->assertSame(
            [$eventOne->id, $eventTwo->id, $eventThree->id],
            $templates->pluck('event_id')->sort()->values()->all()
        );
        $this->assertTrue($templates->every(fn (CertificateTemplate $t) => $t->item_id === null));

        // Editing the copy for event two afterwards must not touch event one or three's rows.
        $eventTwoTemplate = $templates->firstWhere('event_id', $eventTwo->id);
        $eventTwoTemplate->update(['title' => 'Edited For Event Two Only']);
        $this->assertSame('Shared Design', $templates->firstWhere('event_id', $eventOne->id)->fresh()->title);
        $this->assertSame('Shared Design', $templates->firstWhere('event_id', $eventThree->id)->fresh()->title);
    }

    public function test_also_apply_to_event_ids_is_ignored_without_a_primary_event(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin] = $this->makeSahodayaAdminAndEvent();
        $eventTwo = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Second Event', 'event_type' => 'kalolsavam']);

        $response = $this->actingAs($admin)->post(route('sahodaya.certificate-templates.store', [
            'tenantId' => $sahodaya->id,
        ]), [
            'event_type'              => 'fest',
            'event_id'                => null,
            'certificate_type'        => 'participation',
            'title'                   => 'Sahodaya Default',
            'is_active'               => true,
            'also_apply_to_event_ids' => [$eventTwo->id],
        ]);

        $response->assertRedirect();

        $this->assertSame(1, CertificateTemplate::where('tenant_id', $sahodaya->id)
            ->where('title', 'Sahodaya Default')->count());
    }

    /**
     * Regression test: update()'s validate() call previously had no event_id/item_id
     * rules at all, so Illuminate\Http\Request::validate() silently dropped both from
     * $data regardless of what the admin picked in the edit form's Event/Item dropdowns
     * — a template could never actually be moved to a different event after creation.
     */
    public function test_updating_a_template_can_change_its_event(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $eventOne] = $this->makeSahodayaAdminAndEvent();
        $eventTwo = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Second Event', 'event_type' => 'kalolsavam']);

        $template = CertificateTemplate::create([
            'tenant_id' => $sahodaya->id, 'event_type' => 'fest', 'event_id' => $eventOne->id,
            'certificate_type' => 'winner', 'title' => 'Movable Template', 'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('sahodaya.certificate-templates.update', [
            'tenantId' => $sahodaya->id, 'template' => $template->id,
        ]), ['event_id' => $eventTwo->id]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertSame($eventTwo->id, $template->fresh()->event_id);
    }

    public function test_updating_a_template_into_an_occupied_scope_deactivates_the_incumbent_there(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $eventOne] = $this->makeSahodayaAdminAndEvent();
        $eventTwo = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Second Event', 'event_type' => 'kalolsavam']);

        $incumbent = CertificateTemplate::create([
            'tenant_id' => $sahodaya->id, 'event_type' => 'fest', 'event_id' => $eventTwo->id,
            'certificate_type' => 'winner', 'title' => 'Already At Event Two', 'is_active' => true,
        ]);
        $mover = CertificateTemplate::create([
            'tenant_id' => $sahodaya->id, 'event_type' => 'fest', 'event_id' => $eventOne->id,
            'certificate_type' => 'winner', 'title' => 'Moving In', 'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('sahodaya.certificate-templates.update', [
            'tenantId' => $sahodaya->id, 'template' => $mover->id,
        ]), ['event_id' => $eventTwo->id, 'is_active' => true]);

        $response->assertRedirect();
        $response->assertSessionHas('success', fn ($message) => str_contains($message, '1 existing template(s)'));
        $this->assertFalse($incumbent->fresh()->is_active, 'Moving a template into an occupied scope must deactivate the one already there.');
        $this->assertTrue($mover->fresh()->is_active);
        $this->assertSame($eventTwo->id, $mover->fresh()->event_id);
    }
}
