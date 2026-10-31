<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Models\FestSportsAgeGroupConfig;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FestSportsSettingsIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $tenant = Tenant::create(['id' => (string) Str::uuid(), 'name' => 'Sports settings', 'type' => 'sahodaya', 'domain' => Str::uuid().'.test', 'is_active' => true]);
        SahodayaProfile::create(['tenant_id' => $tenant->id, 'prefix' => 'SSI', 'student_data_mode' => 'counts_only', 'sports_age_cutoff_date' => '2026-10-01']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');
        $event = FestEvent::create(['tenant_id' => $tenant->id, 'title' => 'Athletics', 'event_type' => 'sports', 'status' => 'registration_open',
            'event_start' => '2026-11-06', 'event_end' => '2026-11-07', 'schedule_published' => true, 'scoring_locked' => true, 'registration_locked' => true,
            'sports_age_cutoff_date' => '2026-12-01']);
        $this->actingAs($admin);
        return [$tenant, $event, "/sahodaya-admin/{$tenant->id}/events/{$event->id}"];
    }

    public function test_bulk_standby_limits_apply_to_individual_and_team_items_without_changing_squad_rules(): void
    {
        [$tenant, $event, $base] = $this->fixture();
        $solo = FestEventItem::create(['event_id' => $event->id, 'title' => '100m', 'participant_type' => 'individual', 'max_per_school' => 2]);
        $team = FestEventItem::create(['event_id' => $event->id, 'title' => 'Relay', 'participant_type' => 'group', 'min_group_size' => 4, 'max_group_size' => 6, 'criteria_json' => ['min_squad' => 4, 'max_squad' => 6, 'max_subs' => 2, 'standbys' => 1, 'custom' => 'keep']]);
        $other = FestEventItem::create(['event_id' => $event->id, 'title' => 'Long jump', 'participant_type' => 'individual']);
        $this->post("{$base}/items/bulk-caps", ['items' => [['id' => $solo->id, 'standbys' => 3], ['id' => $team->id, 'standbys' => 0]]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3, $solo->fresh()->criteria_json['standbys']);
        $this->assertSame(2, $solo->fresh()->max_per_school);
        $this->assertSame(['min_squad' => 4, 'max_squad' => 6, 'max_subs' => 2, 'standbys' => 0, 'custom' => 'keep'], $team->fresh()->criteria_json);
        $this->assertNull($other->fresh()->criteria_json);
        $this->post("{$base}/items/bulk-caps", ['items' => [['id' => $team->id, 'standbys' => null]]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('standbys', $team->fresh()->criteria_json);
        $this->assertSame(1, \App\Support\FestStandbyLimit::forItem($event, $team->fresh()));
    }

    public function test_bulk_squad_limits_validate_ranges_atomically_and_reject_items_outside_event(): void
    {
        [$tenant, $event, $base] = $this->fixture();
        $solo = FestEventItem::create(['event_id' => $event->id, 'title' => '100m', 'participant_type' => 'individual']);
        $team = FestEventItem::create(['event_id' => $event->id, 'title' => 'Relay', 'participant_type' => 'group', 'min_group_size' => 4, 'max_group_size' => 6, 'criteria_json' => ['min_squad' => 4, 'max_squad' => 6, 'standbys' => 1]]);
        $this->post("{$base}/items/bulk-caps", ['items' => [['id' => $solo->id, 'standbys' => 3], ['id' => $team->id, 'max_group_size' => 2]]])->assertSessionHasErrors('items.1.max_group_size');
        $this->assertNull($solo->fresh()->criteria_json);
        $this->assertSame(6, $team->fresh()->max_group_size);
        $this->post("{$base}/items/bulk-caps", ['items' => [['id' => $team->id, 'min_group_size' => 3, 'max_group_size' => 5, 'max_subs' => 2]]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3, $team->fresh()->min_group_size);
        $this->assertSame(5, $team->fresh()->max_group_size);
        $this->assertSame(1, $team->fresh()->criteria_json['standbys']);
        $this->post("{$base}/items/bulk-caps", ['items' => [['id' => $team->id, 'max_subs' => 9]]])->assertSessionHasErrors('items.0.max_subs');
        $otherEvent = FestEvent::create(['tenant_id' => $tenant->id, 'title' => 'Other', 'event_type' => 'sports']);
        $other = FestEventItem::create(['event_id' => $otherEvent->id, 'title' => 'Other item', 'participant_type' => 'individual']);
        $this->post("{$base}/items/bulk-caps", ['items' => [['id' => $other->id, 'standbys' => 2]]])->assertSessionHasErrors('items.0.id');
    }

    public function test_saving_records_does_not_reset_schedule_or_operational_locks(): void
    {
        [, $event, $base] = $this->fixture();
        $child = FestEvent::create(['tenant_id' => $event->tenant_id, 'title' => 'Region', 'event_type' => 'sports', 'parent_event_id' => $event->id, 'partition_role' => 'region', 'scoring_locked' => false]);
        $this->put("{$base}/settings", ['record_tracking_enabled' => true, 'default_record_prize_label' => 'New record'])
            ->assertSessionDoesntHaveErrors();
        $event->refresh();
        $this->assertTrue($event->schedule_published);
        $this->assertTrue($event->scoring_locked);
        $this->assertTrue($event->registration_locked);
        $this->assertTrue($event->record_tracking_enabled);
        $this->assertFalse($child->refresh()->scoring_locked);
        $this->put("{$base}/settings", ['scoring_locked' => false])->assertSessionDoesntHaveErrors();
        $this->assertFalse($event->refresh()->scoring_locked);
        $this->assertTrue($event->schedule_published);
    }

    public function test_eligibility_suggestion_uses_the_inherited_cutoff_not_the_event_override(): void
    {
        [, , $base] = $this->fixture();
        $this->get("{$base}/settings/eligibility")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('initialTab', 'eligibility')->where('suggestedAgeCutoff', '2026-10-01')->where('defaultCutoffLabel', '1 Oct 2026')
            ->where('classCategorySchemes', [])->where('participationPresets', []));
    }

    public function test_partial_event_date_update_checks_the_existing_start_date(): void
    {
        [, $event, $base] = $this->fixture();
        $this->put($base, ['title' => 'Athletics', 'status' => 'registration_open', 'event_end' => '2026-10-07'])->assertSessionHasErrors('event_end');
        $this->assertSame('2026-11-07', $event->refresh()->event_end->toDateString());
    }

    public function test_used_age_category_cannot_be_deleted_or_removed_by_reset(): void
    {
        [$tenant, $event] = $this->fixture();
        $category = FestSportsAgeGroupConfig::create(['tenant_id' => $tenant->id, 'group_key' => 'u13_custom', 'label' => 'Under 13 custom', 'under_age' => 13, 'is_active' => true]);
        FestEventItem::create(['event_id' => $event->id, 'title' => '100m', 'age_group' => 'u13_custom', 'participant_type' => 'individual', 'is_enabled' => true]);
        $this->delete("/sahodaya-admin/{$tenant->id}/sports/age-groups/{$category->id}")->assertSessionHasErrors('age_category');
        $this->assertDatabaseHas('fest_sports_age_group_configs', ['id' => $category->id]);
        $this->post("/sahodaya-admin/{$tenant->id}/sports-age-groups/reset-defaults")->assertSessionHasErrors('age_category');
        $this->assertDatabaseHas('fest_sports_age_group_configs', ['id' => $category->id]);
    }

    public function test_school_chest_ranges_reject_reversed_bounds_and_other_clusters_schools(): void
    {
        [$tenant, $event, $base] = $this->fixture();
        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'School', 'parent_id' => $tenant->id, 'domain' => Str::uuid().'.test']);
        $this->put("{$base}/school-chest-ranges", ['schools' => [['school_id' => $school->id, 'chest_no_start' => 200, 'chest_no_end' => 100]]])
            ->assertSessionHasErrors('schools.0.chest_no_end');
        $other = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Other school', 'domain' => Str::uuid().'.test']);
        $this->put("{$base}/school-chest-ranges", ['schools' => [['school_id' => $other->id, 'chest_no_start' => 100]]])
            ->assertSessionHasErrors('schools.0.school_id');
        $this->assertDatabaseMissing('fest_school_chest_ranges', ['event_id' => $event->id]);
        $this->put("{$base}/school-chest-ranges", ['schools' => [['school_id' => $school->id, 'chest_no_start' => 100, 'chest_no_end' => 200]]])
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('fest_school_chest_ranges', ['event_id' => $event->id, 'school_id' => $school->id, 'chest_no_end' => 200]);
    }

    public function test_every_sports_settings_section_is_reachable_without_arts_presets(): void
    {
        [, , $base] = $this->fixture();
        foreach (['fees', 'registration', 'participation', 'eligibility', 'records', 'venues', 'numbering', 'volunteers', 'lifecycle', 'locks', 'clone'] as $tab) {
            $this->get("{$base}/settings/{$tab}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
                ->where('initialTab', $tab)->where('event.event_type', 'sports')->where('participationPresets', []));
        }
    }

    public function test_sports_master_navigation_omits_arts_only_dimensions(): void
    {
        [$tenant] = $this->fixture();
        $this->get("/sahodaya-admin/{$tenant->id}/taxonomy-masters?dimension=sport_discipline&program=sports-meet")
            ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
                ->has('dimensions.sport_discipline')->missing('dimensions.arts_category')->missing('dimensions.class_group')->missing('dimensions.stage_type'));
    }

    public function test_used_venues_and_stages_cannot_be_removed_from_existing_schedules(): void
    {
        [$tenant, $event, $base] = $this->fixture();
        $venue = \App\Models\FestVenue::create(['tenant_id' => $tenant->id, 'event_id' => $event->id, 'name' => 'Track']);
        $stage = \App\Models\FestStage::create(['event_id' => $event->id, 'venue_id' => $venue->id, 'name' => 'Lane A']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => '100m', 'participant_type' => 'individual', 'is_enabled' => true]);
        \App\Models\FestSchedule::create(['event_id' => $event->id, 'item_id' => $item->id, 'stage_id' => $stage->id, 'venue_id' => $venue->id, 'scheduled_at' => '2026-11-06 09:00:00']);
        $this->delete("{$base}/stages/{$stage->id}")->assertSessionHasErrors('stage');
        $this->delete("{$base}/venues/{$venue->id}")->assertSessionHasErrors('venue');
        $this->assertDatabaseHas('fest_stages', ['id' => $stage->id]);
        $this->assertDatabaseHas('fest_venues', ['id' => $venue->id]);
    }

    public function test_saving_item_dates_without_a_head_id_does_not_clear_its_head(): void
    {
        [, $event, $base] = $this->fixture();
        $head = FestItemHead::create(['tenant_id' => $event->tenant_id, 'event_id' => $event->id, 'name' => 'Athletics', 'slug' => 'athletics']);
        $item = FestEventItem::create(['event_id' => $event->id, 'head_id' => $head->id, 'title' => '100m', 'participant_type' => 'individual', 'is_enabled' => true]);
        $this->patch("{$base}/items/windows/bulk", ['rows' => [['id' => $item->id, 'reg_start' => '2026-10-01', 'reg_end' => '2026-10-31']]])
            ->assertSessionDoesntHaveErrors();
        $this->assertSame($head->id, $item->refresh()->head_id);
    }
}
