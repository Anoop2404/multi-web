<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\AcademicYearRecord;
use App\Models\BoardResult;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BoardResultProofPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_sahodaya_admin_can_preview_board_result_proof_inline(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
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

        $year = AcademicYearRecord::create([
            'label' => '2026-27',
            'start_date' => '2026-06-01',
            'end_date' => '2027-05-31',
            'status' => 'active',
        ]);

        $boardResult = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 12,
            'academic_year' => $year->label,
            'academic_year_id' => $year->id,
            'examination_type' => BoardResult::EXAM_AISSCE,
            'status' => BoardResult::STATUS_SUBMITTED,
        ]);

        $proofPath = "board-results/{$school->id}/{$boardResult->id}/proof.docx";
        Storage::disk('shared')->put($proofPath, 'proof-contents');
        $boardResult->update([
            'result_pdf_path' => $proofPath,
            'result_pdf_disk' => 'shared',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $sahodaya->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('sahodaya_admin');

        $response = $this->actingAs($admin)->get(
            "/sahodaya-admin/{$sahodaya->id}/board-results/{$boardResult->id}/pdf?preview=1"
        );

        $response->assertOk();
        $response->assertHeaderContains('Content-Disposition', 'inline');
    }
}
