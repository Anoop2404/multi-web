<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * HandleInertiaRequests shared 'importErrors' (camelCase) under a session key that was
 * never actually written -- every importer that flashes row-skip reasons
 * (FestRegistrationReviewController::bulkApprove/bulkReject/importStore,
 * FestAttendanceController::importStore, FestScheduleController's imports,
 * SchoolAdmin\TeacherController, SchoolAdmin\FestRegistrationController) writes under
 * 'importErrors' (matching the sibling 'importResult' key's casing), while the
 * middleware only ever read 'import_errors' (snake_case, McqExamOpsController's own,
 * different key) -- so the "why were N rows skipped" detail list silently never
 * reached the page, even though the main success/error flash worked fine. This is a
 * middleware/props test, not a controller-specific one -- it directly asserts the
 * flash pipeline itself, independent of any one importer's business logic.
 */
class FestImportErrorsFlashTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Import Errors Sahodaya',
            'domain' => 'import-errors-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'IE', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Import Errors Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        return [$sahodaya, $event, $admin];
    }

    public function test_importerrors_flashed_under_camelcase_key_reaches_the_page(): void
    {
        [$sahodaya, $event, $admin] = $this->fixture();

        // Simulates exactly what FestRegistrationReviewController::bulkApprove() etc.
        // already do: ->with('importErrors', [...]) on a back()/redirect() response.
        $this->actingAs($admin)
            ->withSession(['importErrors' => ['Row 3: unknown school_prefix "XYZ"', 'Row 7: item not found']])
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/registrations")
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.importErrors', ['Row 3: unknown school_prefix "XYZ"', 'Row 7: item not found']));
    }

    /** The pre-existing McqExamOpsController key must keep working unchanged -- this fix is additive, not a rename. */
    public function test_legacy_snake_case_import_errors_key_still_works(): void
    {
        [$sahodaya, $event, $admin] = $this->fixture();

        $this->actingAs($admin)
            ->withSession(['import_errors' => ['legacy row error']])
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/registrations")
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.import_errors', ['legacy row error']));
    }
}
