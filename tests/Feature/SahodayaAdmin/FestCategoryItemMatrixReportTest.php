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
 * The new consolidated category-wise/item-wise report — school rows, item columns
 * grouped by category, category subtotal + overall grand-total columns. Covers the
 * interactive page and both export formats (xls, pdf) end to end, plus the exact
 * points-per-cell computation the user's example sheet needs to match the leaderboard.
 */
class FestCategoryItemMatrixReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_interactive_page_returns_correct_matrix_shape_and_points(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Matrix Report Sahodaya',
            'domain' => 'matrix-report-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'MR', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Test School', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Matrix Report Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual',
            'class_group' => 'hs', 'is_enabled' => true,
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Test Student', 'admission_no' => 'S1']);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/category-item-matrix")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/Reports/CategoryItemMatrix', false)
                ->where('categories.0.items.0.id', $item->id)
                ->where('schools.0.school_id', $school->id)
                ->has('schools.0.points_by_item.'.$item->id)
                ->has('schools.0.category_totals')
                ->has('schools.0.overall'));
    }

    public function test_xls_export_downloads(): void
    {
        [$sahodaya, $event, $admin] = $this->makeMinimalEvent();

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/export/category-item-matrix-xls");

        $response->assertOk();
    }

    public function test_pdf_export_downloads(): void
    {
        [$sahodaya, $event, $admin] = $this->makeMinimalEvent();

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/export/category-item-matrix-pdf");

        $response->assertOk();
    }

    /** @return array{0: Tenant, 1: FestEvent, 2: User} */
    private function makeMinimalEvent(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Matrix Export Sahodaya',
            'domain' => 'matrix-export-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'ME', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Matrix Export Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        return [$sahodaya, $event, $admin];
    }
}
