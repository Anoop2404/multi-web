<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\AcademicYearRecord;
use App\Models\BoardResult;
use App\Models\BoardResultCertificationPackage;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\Topper;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BoardResultLeadershipReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, principal: User, unauthorized: User} */
    private function makeSchool(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'lead-review-http-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'TS',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Test School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        AcademicYearRecord::firstOrCreate(
            ['label' => '2026-27'],
            [
                'start_date' => '2026-06-01',
                'end_date' => '2027-05-31',
                'status' => 'active',
            ]
        );

        $principal = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $principal->assignRole('school_principal');

        // No principal/vice-principal/school_admin role — represents any other
        // authenticated portal login (e.g. an event coordinator) that can reach
        // this page but must not be able to move the certification forward.
        $unauthorized = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);

        return compact('sahodaya', 'school', 'principal', 'unauthorized');
    }

    private function makeBoardResultWithProof(Tenant $school, int $class = 10): BoardResult
    {
        Storage::disk('shared')->put("board-results/{$school->id}/proof.pdf", 'proof');

        $result = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => $class,
            'examination_type' => BoardResult::examinationTypeForClass($class),
            'academic_year' => '2026-27',
            'total_appeared' => 10,
            'pass_count' => 10,
            'pass_percent' => 100.0,
            'distinctions' => 2,
            'first_class' => 3,
            'status' => BoardResult::STATUS_DRAFT,
            'result_pdf_path' => "board-results/{$school->id}/proof.pdf",
            'result_pdf_disk' => 'shared',
        ]);

        Topper::create([
            'board_result_id' => $result->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_OVERALL,
            'name' => 'Top Student',
            'gender' => 'female',
            'roll_no' => '1001',
            'marks_obtained' => 480,
            'total_marks' => 500,
            'percentage' => 96,
            'rank' => 1,
        ]);

        return $result;
    }

    public function test_viewing_the_review_page_does_not_advance_the_package(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResultWithProof($school, 10);

        $base = "/school-admin/{$school->id}/board-results/{$result->id}";

        // Data is complete enough that the old code would have auto-advanced
        // draft -> awaiting_leadership_review -> awaiting_report_signatures
        // on this single GET. It must now stay exactly at draft.
        $this->actingAs($principal)->get("{$base}/principal-verification")->assertOk();

        $package = BoardResultCertificationPackage::where('board_result_id', $result->id)->first();
        $this->assertNotNull($package, 'show() should still get-or-create a package row to render against.');
        $this->assertSame(BoardResultCertificationPackage::STATUS_DRAFT, $package->status);

        // Viewing it again changes nothing either — side-effect-free means idempotent.
        $this->actingAs($principal)->get("{$base}/principal-verification")->assertOk();
        $this->assertSame(BoardResultCertificationPackage::STATUS_DRAFT, $package->fresh()->status);
    }

    public function test_request_review_is_blocked_for_a_user_without_leadership_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');
        ['school' => $school, 'unauthorized' => $unauthorized] = $this->makeSchool();
        $result = $this->makeBoardResultWithProof($school, 10);

        $response = $this->actingAs($unauthorized)
            ->post("/school-admin/{$school->id}/board-results/{$result->id}/request-leadership-review");

        $response->assertForbidden();

        $package = BoardResultCertificationPackage::where('board_result_id', $result->id)->first();
        $this->assertNull($package, 'An unauthorized request must not create or advance a package.');
    }

    public function test_request_review_succeeds_for_the_principal(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResultWithProof($school, 10);

        $this->actingAs($principal)
            ->post("/school-admin/{$school->id}/board-results/{$result->id}/request-leadership-review")
            ->assertRedirect();

        $package = BoardResultCertificationPackage::where('board_result_id', $result->id)->firstOrFail();
        $this->assertSame(BoardResultCertificationPackage::STATUS_AWAITING_LEADERSHIP_REVIEW, $package->status);
    }
}
