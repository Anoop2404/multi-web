<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestStateProgram;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression test: sahodaya_customized_at (and its fee_customized_at sibling) were
 * stamped via $event->updateQuietly(['sahodaya_customized_at' => now()]) in
 * FestEventController::update() since 2026-08-13, but the column was never added to
 * FestEvent::$fillable — mass assignment silently dropped it on every save, so the
 * "🔧 Locally customised" indicator badge it drives never actually turned on in
 * practice, despite the backend/frontend plumbing for it otherwise being complete.
 */
class FestEventCustomizationTimestampsPersistTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_a_state_seeded_field_stamps_sahodaya_customized_at(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Customization Test Sahodaya',
            'domain'    => 'customization-test.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'CT',
            'student_data_mode' => 'counts_only',
        ]);

        $program = FestStateProgram::create([
            'title'          => 'State Kalolsavam',
            'event_type'     => 'kalolsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'published',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id'         => $sahodaya->id,
            'title'             => 'State-linked Event',
            'event_type'        => 'kalolsavam',
            'level_round'       => 'sahodaya',
            'state_program_id'  => $program->id,
            'status'            => 'draft',
            'venue'             => 'Original Venue',
        ]);

        $this->assertNull($event->sahodaya_customized_at);

        $response = $this->actingAs($admin)->put(route('sahodaya.events.update', [
            'tenantId' => $sahodaya->id,
            'event'    => $event->id,
        ]), [
            'title'  => 'State-linked Event',
            'status' => 'draft',
            'venue'  => 'Sahodaya-chosen Venue',
        ]);

        $response->assertSessionDoesntHaveErrors();

        $fresh = $event->fresh();
        $this->assertSame('Sahodaya-chosen Venue', $fresh->venue);
        $this->assertNotNull($fresh->sahodaya_customized_at, 'sahodaya_customized_at must actually persist, not be silently dropped by mass assignment.');
        $this->assertTrue($fresh->isCustomizedBySahodaya());
    }
}
