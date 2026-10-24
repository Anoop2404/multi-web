<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\Tenant;
use App\Services\Events\ProgramHubDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolSportsHubEventListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_programs_respects_sports_context_and_opens_new_sport_in_its_workspace(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $sahodaya = Tenant::create(['id' => 'programs-hub', 'name' => 'Programs Sahodaya', 'type' => 'sahodaya', 'is_active' => true]);
        \App\Models\SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'PRG', 'student_data_mode' => 'counts_only']);
        $school = Tenant::create(['id' => 'programs-school', 'name' => 'School', 'type' => 'school', 'parent_id' => $sahodaya->id, 'is_active' => true, 'membership_status' => 'approved']);
        $user = \App\Models\User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $user->assignRole('school_admin');
        $url = '/school-admin/'.$school->id.'/fest-programs';
        $this->actingAs($user)->get($url.'?type=sports')->assertOk()->assertInertia(fn ($page) => $page->component('School/Events/Programs', false)->where('initialType', 'sports'));
        $response = $this->post($url, ['title' => 'Annual Sports Day', 'event_type' => 'sports'])->assertSessionHasNoErrors();
        $event = FestEvent::where('conducting_school_id', $school->id)->where('title', 'Annual Sports Day')->firstOrFail();
        $this->assertSame('sports', $event->event_type);
        $this->assertSame('draft', $event->status);
        $response->assertRedirect('/school-admin/'.$school->id.'/sports/my-event/'.$event->id);
    }

    public function test_hub_lists_every_open_event_beyond_six_without_exposing_drafts_or_other_tenants(): void
    {
        $sahodaya = Tenant::create(['id' => 'sports-hub', 'name' => 'Sports Sahodaya', 'type' => 'sahodaya']);
        $other = Tenant::create(['id' => 'other-hub', 'name' => 'Other Sahodaya', 'type' => 'sahodaya']);
        $school = Tenant::create(['id' => 'sports-school', 'name' => 'Sports School', 'type' => 'school', 'parent_id' => $sahodaya->id]);
        $ids = [];
        foreach (range(1, 9) as $number) {
            $ids[] = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Sport '.$number, 'event_type' => 'sports', 'level_round' => 'sahodaya', 'status' => 'registration_open'])->id;
        }
        FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Draft sport', 'event_type' => 'sports', 'level_round' => 'sahodaya', 'status' => 'draft']);
        FestEvent::create(['tenant_id' => $other->id, 'title' => 'Other sport', 'event_type' => 'sports', 'level_round' => 'sahodaya', 'status' => 'registration_open']);
        $data = app(ProgramHubDataService::class)->schoolFestHub($school, 'sports-meet');
        $this->assertCount(9, $data['events']);
        $this->assertSame(9, $data['stats']['open_events']);
        $this->assertEqualsCanonicalizing($ids, array_column($data['events'], 'id'));
    }
}
