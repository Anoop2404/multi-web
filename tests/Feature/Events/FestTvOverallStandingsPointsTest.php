<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestGradePointService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Two changes to the public TV screen (FestPortalController::tv()):
 *
 * 1. The Overall/Category Standings medal columns showed raw gold/silver/bronze COUNTS
 *    (how many times a school placed 1st/2nd/3rd), duplicating the separate Points
 *    column with no real information about how much each position actually contributed.
 *    Now sums FestGradePointService::pointsForMark() per position instead — the same
 *    per-mark point computation the standings' own Points total already uses.
 *
 * 2. Added FestEvent.tv_show_overall_standings (default true) so a Sahodaya admin can
 *    turn off the fest-wide Overall Standings slide while category-wise slides keep
 *    rotating regardless — verified here via the real settings-save route rather than
 *    the TV route itself, since the public portal is resolved by tenant *domain*
 *    (InitializeTenancyByRequestHost) which this test suite has no existing pattern for
 *    driving; the tv() controller's own conditional is a one-line `if`, low-risk enough
 *    that covering its two collaborators (the point sum, and the persisted flag) gives
 *    confidence without new tenant-domain test infrastructure.
 */
class FestTvOverallStandingsPointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sports_rank_points_use_the_athletics_standard_fallback_with_no_custom_config(): void
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'TV Points Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'TV Points Sports Meet', 'event_type' => 'sports',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $gold = new FestMark(['event_id' => $event->id, 'position' => 1]);
        $silver = new FestMark(['event_id' => $event->id, 'position' => 2]);
        $bronze = new FestMark(['event_id' => $event->id, 'position' => 3]);

        $service = app(FestGradePointService::class);

        $this->assertSame(8, $service->pointsForMark($event, $gold));
        $this->assertSame(7, $service->pointsForMark($event, $silver));
        $this->assertSame(6, $service->pointsForMark($event, $bronze));
    }

    public function test_tv_show_overall_standings_defaults_true_and_can_be_turned_off(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'TV Toggle Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'TVT', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'TV Toggle Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $this->assertTrue($event->fresh()->tv_show_overall_standings);

        $response = $this->actingAs($admin)->put(route('sahodaya.events.settings.update', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'tv_show_overall_standings' => false,
        ]);

        $response->assertRedirect();
        $this->assertFalse($event->fresh()->tv_show_overall_standings);
    }
}
