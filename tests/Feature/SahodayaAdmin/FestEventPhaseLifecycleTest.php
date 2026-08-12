<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression test for LIFE-05 (functional audit, 2026-08-11/12):
 * FestEventPhase lifecycle columns (status, registration_open/close,
 * scoring_locked, results_published, etc.) previously had zero write path
 * anywhere in the app — every phase was permanently stuck at its migration
 * defaults. Proves both the general update() endpoint now writes lifecycle
 * fields, and the new quickStatus() endpoint enforces the same transition
 * guard FestEvent uses.
 */
class FestEventPhaseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function makeEventWithPhase(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Phase Lifecycle Sahodaya',
            'domain'    => 'phase-lifecycle.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'PL',
            'student_data_mode' => 'counts_only',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id'          => $sahodaya->id,
            'title'              => 'Phase Mode Event',
            'event_type'         => 'kalotsavam',
            'level_round'        => 'sahodaya',
            'status'             => 'published',
            'phase_mode_enabled' => true,
        ]);

        $phase = FestEventPhase::create([
            'event_id'   => $event->id,
            'name'       => 'Prelims',
            'code'       => 'PRE',
            'sort_order' => 1,
            'status'     => 'draft',
        ]);

        return compact('sahodaya', 'admin', 'event', 'phase');
    }

    public function test_update_endpoint_now_writes_lifecycle_fields(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'phase' => $phase] = $this->makeEventWithPhase();

        $this->assertNull($phase->registration_open);
        $this->assertFalse((bool) $phase->registration_locked);

        $response = $this->actingAs($admin)->put(route('sahodaya.events.phases.update', [
            'tenantId' => $sahodaya->id,
            'event'    => $event->id,
            'phase'    => $phase->id,
        ]), [
            'name'               => 'Prelims',
            'registration_open'  => '2026-01-01 00:00:00',
            'registration_close' => '2026-01-10 23:59:59',
            'registration_locked'=> true,
            'scoring_locked'     => true,
        ]);

        $response->assertSessionDoesntHaveErrors();

        $phase->refresh();
        $this->assertNotNull($phase->registration_open);
        $this->assertTrue((bool) $phase->registration_locked);
        $this->assertTrue((bool) $phase->scoring_locked);
    }

    public function test_quick_status_transitions_through_the_guarded_matrix(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'phase' => $phase] = $this->makeEventWithPhase();

        $this->assertSame('draft', $phase->status);

        // draft -> published is legal.
        $this->actingAs($admin)->post(route('sahodaya.events.phases.quick-status', [
            'tenantId' => $sahodaya->id,
            'event'    => $event->id,
            'phase'    => $phase->id,
        ]), ['status' => 'published'])->assertSessionDoesntHaveErrors();

        $this->assertSame('published', $phase->fresh()->status);

        // published -> completed is NOT legal per the shared FEST_EVENT_TRANSITIONS
        // matrix (must pass through registration_open/ongoing first).
        $response = $this->actingAs($admin)->post(route('sahodaya.events.phases.quick-status', [
            'tenantId' => $sahodaya->id,
            'event'    => $event->id,
            'phase'    => $phase->id,
        ]), ['status' => 'completed']);

        $response->assertSessionHasErrors('status');
        $this->assertSame('published', $phase->fresh()->status);
    }

    public function test_effective_lifecycle_reads_back_what_quick_status_and_update_wrote(): void
    {
        ['event' => $event, 'phase' => $phase, 'sahodaya' => $sahodaya, 'admin' => $admin] = $this->makeEventWithPhase();

        app(\App\Services\Events\FestEventPhaseService::class)->updatePhase($phase, ['results_published' => true]);

        $item = \App\Models\FestEventItem::create([
            'event_id' => $event->id,
            'title'    => 'Solo Song',
            'category' => 'music',
            'phase_id' => $phase->id,
            'is_enabled' => true,
        ]);

        $lifecycle = app(\App\Services\Events\FestPhaseLifecycleService::class)->effectiveLifecycleForItem($item->fresh());

        $this->assertTrue($lifecycle->results_published);
        $this->assertSame('phase:'.$phase->id, $lifecycle->source);
    }
}
