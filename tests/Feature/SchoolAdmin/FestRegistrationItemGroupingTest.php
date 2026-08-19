<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for the item-taxonomy fix: FestRegistrationController's item
 * grouping/serialization must classify items from the real stage_type/participant_type
 * columns, never by guessing from English keywords in the title. Every item title below
 * is deliberately non-English and contains none of the old heuristic's keywords
 * (painting/drawing/essay/quiz/etc.) — if the fix regressed to keyword matching, these
 * would all silently fall through to the wrong bucket.
 */
class FestRegistrationItemGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_groups_and_serializes_items_from_real_columns_not_title_keywords(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Grouping Test Sahodaya',
            'domain' => 'grouping-test-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'GT',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Grouping Test School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Grouping Test Kalotsav',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $offStage = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Chithrarachana',
            'stage_type' => 'off_stage',
            'participant_type' => 'individual',
            'category' => 'general',
            'is_enabled' => true,
        ]);

        $onStageGroup = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Nadakam',
            'stage_type' => 'on_stage',
            'participant_type' => 'team',
            'category' => 'drama',
            'is_enabled' => true,
        ]);

        $onStageSingle = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Nrithyam',
            'stage_type' => 'on_stage',
            'participant_type' => 'individual',
            'category' => 'dance',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($schoolAdmin)->get(route('school.kalotsav.event.registration', [
            'tenantId' => $school->id,
            'event' => $event->id,
        ]));

        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $hydratedEvent = collect($props['events'])->first();
        $items = collect($hydratedEvent['items']);

        $this->assertSame('off_stage', $items->firstWhere('id', $offStage->id)['stage_type']);
        $this->assertSame('general', $items->firstWhere('id', $offStage->id)['category']);
        $this->assertSame('team', $items->firstWhere('id', $onStageGroup->id)['participant_type']);

        $grouped = $hydratedEvent['items_grouped'];
        $this->assertSame([$offStage->id], collect($grouped['off_stage'])->pluck('id')->all());
        // "on_stage" is every on_stage item regardless of participant_type, and "group" is
        // every group/team item regardless of stage_type — the two buckets overlap by
        // design (pre-existing groupItemsForEvent() behavior, unchanged by this fix), so
        // the on-stage group item legitimately appears in both.
        $this->assertEqualsCanonicalizing([$onStageGroup->id, $onStageSingle->id], collect($grouped['on_stage'])->pluck('id')->all());
        $this->assertSame([$onStageGroup->id], collect($grouped['group'])->pluck('id')->all());

        $labels = $hydratedEvent['item_group_labels'];
        $this->assertSame('On Stage', $labels['on_stage']);
        $this->assertSame('Off Stage', $labels['off_stage']);
    }
}
