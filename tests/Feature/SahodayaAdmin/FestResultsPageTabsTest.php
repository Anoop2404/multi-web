<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The admin Results page was reorganized into three tabs (Item Picker / Publish
 * Status / Point Tables) and gained a gender label + category-wise point boards
 * alongside the existing overall scoreboard. Covers the two new data pieces the
 * controller now sends (gender_label on itemSummaries, categoryBoards) rather than
 * the Vue tab UI itself, which has no server round trip to test.
 */
class FestResultsPageTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_page_sends_gender_label_and_category_boards(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Results Tabs Sahodaya',
            'domain' => 'results-tabs-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'RT', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Results Tabs School', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Results Tabs Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $openItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'Open Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
        $girlsItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'Girls Item', 'participant_type' => 'group', 'gender' => 'female', 'class_group' => 'lp', 'is_enabled' => true]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        foreach ([$openItem, $girlsItem] as $i => $item) {
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => "Student {$i}", 'admission_no' => "RT{$i}"]);
            $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
            $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
            FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);
        }

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/results")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/Results', false)
                ->where('itemSummaries', function ($summaries) {
                    $byTitle = collect($summaries)->keyBy('title');

                    return $byTitle['Open Item']['gender_label'] === 'Open'
                        && $byTitle['Open Item']['type_label'] === 'Individual'
                        && $byTitle['Girls Item']['gender_label'] === 'Girls'
                        && $byTitle['Girls Item']['type_label'] === 'Group';
                })
                ->where('categoryBoards', fn ($boards) => collect($boards)->pluck('key')->contains('hs')
                    && collect($boards)->pluck('key')->contains('lp')));
    }
}
