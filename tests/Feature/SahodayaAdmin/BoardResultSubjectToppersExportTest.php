<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\BoardResult;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\Topper;
use App\Models\TopperSubjectMark;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A subject-entry Topper's subject_marks map is expected to hold exactly one
 * {subject: marks} pair (see BoardResultVerificationController::exportTopperVerification()'s
 * comment on the ENTRY_SUBJECT branch), but nothing enforces that at the data layer — rows
 * with more than one subject attached exist in practice. Confirms the per-subject sheet in
 * the Excel export shows only that sheet's own subject's marks for such a topper, not every
 * subject attached to the record.
 */
class BoardResultSubjectToppersExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_sheet_shows_only_its_own_subject_marks_even_when_a_topper_has_several(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Subject Export Sahodaya',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'SES',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Subject Export School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $boardResult = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 12,
            'academic_year' => '2026-27',
            'examination_type' => BoardResult::EXAM_AISSCE,
            'status' => BoardResult::STATUS_SUBMITTED,
        ]);

        // Anomalous row: entry_type=subject but has TWO subjects attached — the exact
        // shape reported live (a student on the "Business Studies" sheet also showing
        // Economics and Accountancy marks in that same row).
        $multiSubjectTopper = Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_SUBJECT,
            'name' => 'Sarabi Abdulla',
            'roll_no' => '24642636',
            'verification_status' => 'pending',
        ]);
        TopperSubjectMark::create([
            'topper_id' => $multiSubjectTopper->id,
            'tenant_id' => $school->id,
            'subject_label' => 'Business Studies',
            'marks' => 98,
        ]);
        TopperSubjectMark::create([
            'topper_id' => $multiSubjectTopper->id,
            'tenant_id' => $school->id,
            'subject_label' => 'Economics',
            'marks' => 97,
        ]);

        // A clean single-subject Economics topper, to confirm it isn't affected.
        $economicsTopper = Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_SUBJECT,
            'name' => 'Clean Economics Student',
            'roll_no' => '24642700',
            'verification_status' => 'pending',
        ]);
        TopperSubjectMark::create([
            'topper_id' => $economicsTopper->id,
            'tenant_id' => $school->id,
            'subject_label' => 'Economics',
            'marks' => 95,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $response = $this->actingAs($admin)->get(
            "/sahodaya-admin/{$sahodaya->id}/board-results/verification/subjects/export?class=12&status=all"
        );

        $response->assertOk();
        $xml = $response->streamedContent();

        // Whichever sheet the multi-subject topper landed on (grouping is by first
        // attached subject — not something this test needs to pin down), that sheet's
        // row for them must show ONLY that sheet's subject, never the other one.
        $this->assertMatchesRegularExpression(
            '/<Worksheet ss:Name="Business Studies">.*?Sarabi Abdulla.*?Business Studies: 98.*?<\/Worksheet>/s',
            $xml,
        );
        $businessStudiesSheet = $this->extractSheet($xml, 'Business Studies');
        $this->assertStringNotContainsString('Economics: 97', $businessStudiesSheet, 'The Business Studies sheet must not leak this topper\'s Economics marks.');

        $economicsSheet = $this->extractSheet($xml, 'Economics');
        $this->assertStringContainsString('Clean Economics Student', $economicsSheet);
        $this->assertStringContainsString('Economics: 95', $economicsSheet);
        $this->assertStringNotContainsString('Business Studies: 98', $economicsSheet, 'The Economics sheet must not show Business Studies marks.');
    }

    private function extractSheet(string $xml, string $name): string
    {
        preg_match('/<Worksheet ss:Name="'.preg_quote($name, '/').'">(.*?)<\/Worksheet>/s', $xml, $matches);

        return $matches[1] ?? '';
    }
}
