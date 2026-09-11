<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestParticipationPolicy;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The "Save policy" button on Settings > Participation posts the whole form in one
 * request, including whatever preset_key the dropdown currently shows (which is almost
 * always non-empty, since events are seeded with a preset at creation). The controller
 * must only treat that as "apply this preset's defaults" when the preset actually changed
 * — otherwise every ordinary save (e.g. toggling a single checkbox) with the preset
 * dropdown left untouched would silently discard the submitted custom values and reset
 * everything back to the preset's raw defaults.
 */
class FestParticipationPolicySaveTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User, event: FestEvent} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Participation Policy Sahodaya',
            'domain' => 'participation-policy.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'PP', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Participation Policy Event',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
        ]);

        FestParticipationPolicy::create([
            'tenant_id' => $sahodaya->id,
            'event_id' => $event->id,
            'class_group' => null,
            'scope' => 'event',
            'level_round' => 'sahodaya',
            'preset_key' => 'cksc_sahodaya_cluster',
            'require_fee_before_approval' => true,
            'one_entry_per_item_per_school' => true,
            'is_active' => true,
        ]);

        return compact('sahodaya', 'admin', 'event');
    }

    private function savePolicyForm(Tenant $sahodaya, User $admin, FestEvent $event, array $payload)
    {
        return $this->actingAs($admin)->post(
            route('sahodaya.events.participation-policy.store', ['tenantId' => $sahodaya->id, 'event' => $event->id]),
            $payload
        );
    }

    public function test_saving_with_the_same_preset_still_shown_persists_the_edited_checkbox(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->fixture();

        $response = $this->savePolicyForm($sahodaya, $admin, $event, [
            'preset_key' => 'cksc_sahodaya_cluster',
            'require_fee_before_approval' => false,
            'one_entry_per_item_per_school' => true,
        ]);

        $response->assertSessionDoesntHaveErrors();

        $policy = FestParticipationPolicy::where('event_id', $event->id)->whereNull('class_group')->first();
        $this->assertFalse((bool) $policy->require_fee_before_approval);
    }

    public function test_switching_to_a_different_preset_resets_limits_to_that_presets_defaults(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->fixture();

        $response = $this->savePolicyForm($sahodaya, $admin, $event, [
            'preset_key' => 'mcs_kalotsav',
            'require_fee_before_approval' => false,
            'one_entry_per_item_per_school' => true,
        ]);

        $response->assertSessionHas('success', 'Participation policy preset applied.');

        $policy = FestParticipationPolicy::where('event_id', $event->id)->whereNull('class_group')->first();
        $this->assertSame('mcs_kalotsav', $policy->preset_key);
        $this->assertSame(2, $policy->max_group_per_student);
    }

    public function test_saving_custom_limits_without_ever_touching_the_preset_dropdown_persists_them(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->fixture();

        $response = $this->savePolicyForm($sahodaya, $admin, $event, [
            'preset_key' => 'cksc_sahodaya_cluster',
            'max_total_per_student' => 5,
            'require_fee_before_approval' => true,
            'one_entry_per_item_per_school' => true,
        ]);

        $response->assertSessionDoesntHaveErrors();

        $policy = FestParticipationPolicy::where('event_id', $event->id)->whereNull('class_group')->first();
        $this->assertSame(5, $policy->max_total_per_student);
    }
}
