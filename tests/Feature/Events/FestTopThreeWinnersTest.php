<?php

namespace Tests\Feature\Events;

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
use Tests\TestCase;
use ZipArchive;

class FestTopThreeWinnersTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_three_winners_pdf_and_csv_generation(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Top 3 Test Sahodaya',
            'domain' => 'top3-test.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'T3', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'St. Jude School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $schoolClass = SchoolClass::create([
            'tenant_id' => $school->id,
            'name' => 'Class 10-A',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Kalotsav 2026',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
            'results_published' => false,
        ]);

        // 1. Individual Item
        $individualItem = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Classical Music Solo',
            'category' => 'music',
            'item_code' => 'MUS101',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);

        // Create 4 participants: ranks 1, 2, 3, and 4 (rank 4 should be excluded)
        foreach ([1 => 'Alpha', 2 => 'Beta', 3 => 'Gamma', 4 => 'Delta'] as $rank => $studentName) {
            $student = Student::create([
                'tenant_id' => $school->id,
                'school_class_id' => $schoolClass->id,
                'name' => $studentName,
                'reg_no' => 'REG-' . $studentName,
            ]);

            $reg = FestRegistration::create([
                'event_id' => $event->id,
                'item_id' => $individualItem->id,
                'school_id' => $school->id,
                'status' => 'approved',
            ]);

            $participant = FestParticipant::create([
                'registration_id' => $reg->id,
                'student_id' => $student->id,
                'participant_type' => 'student',
                'participant_role' => 'performer',
                'chest_no' => 100 + $rank,
            ]);

            FestMark::create([
                'event_id' => $event->id,
                'item_id' => $individualItem->id,
                'participant_id' => $participant->id,
                'position' => $rank,
                'score' => 100 - $rank,
                'grade' => 'A',
            ]);
        }

        // 2. Group/Team Item: multiple students in the same team registration, position 1
        $groupItem = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Group Dance',
            'category' => 'dance',
            'item_code' => 'DNC201',
            'participant_type' => 'group',
            'is_enabled' => true,
        ]);

        $groupReg = FestRegistration::create([
            'event_id' => $event->id,
            'item_id' => $groupItem->id,
            'school_id' => $school->id,
            'status' => 'approved',
        ]);

        $teamStudent1 = Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $schoolClass->id,
            'name' => 'Team Dancer 1',
            'reg_no' => 'TD-1',
        ]);
        $teamStudent2 = Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $schoolClass->id,
            'name' => 'Team Dancer 2',
            'reg_no' => 'TD-2',
        ]);

        $teamP1 = FestParticipant::create([
            'registration_id' => $groupReg->id,
            'student_id' => $teamStudent1->id,
            'participant_type' => 'student',
            'participant_role' => 'performer',
            'chest_no' => 501,
        ]);

        $teamP2 = FestParticipant::create([
            'registration_id' => $groupReg->id,
            'student_id' => $teamStudent2->id,
            'participant_type' => 'student',
            'participant_role' => 'performer',
            'chest_no' => 501,
        ]);

        FestMark::create([
            'event_id' => $event->id,
            'item_id' => $groupItem->id,
            'participant_id' => $teamP1->id,
            'position' => 1,
            'score' => 95,
            'grade' => 'A',
        ]);

        // Request PDF
        $pdfResponse = $this->actingAs($admin)->get(route('sahodaya.events.results.top-three-winners', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]));

        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('content-type', 'application/pdf');

        // Request CSV
        $csvResponse = $this->actingAs($admin)->get(route('sahodaya.events.results.top-three-winners', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
            'csv' => '1',
        ]));

        $csvResponse->assertOk();
        $this->assertStringContainsString('application/vnd.ms-excel', $csvResponse->headers->get('content-type'));
        $content = $csvResponse->streamedContent();

        // Individual item ranks 1, 2, 3 should be present, rank 4 excluded
        $this->assertStringContainsString('Alpha', $content);
        $this->assertStringContainsString('Beta', $content);
        $this->assertStringContainsString('Gamma', $content);
        $this->assertStringNotContainsString('Delta', $content);

        // Group item should combine team members into single row
        $this->assertStringContainsString('Team Dancer 1 &amp; Team Dancer 2', $content);
    }

    public function test_csv_and_docx_show_the_short_student_id_not_the_full_reg_no(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Student ID Test Sahodaya',
            'domain' => 'student-id-test.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'SID', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Student ID School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class 10-A']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav 2027', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SNG1',
            'participant_type' => 'individual', 'is_enabled' => true,
        ]);

        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'name' => 'Winner One', 'reg_no' => 'STU/27/1111',
        ]);
        $reg = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create([
            'registration_id' => $reg->id, 'student_id' => $student->id,
            'participant_type' => 'student', 'participant_role' => 'performer', 'chest_no' => 201,
        ]);
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'score' => 99, 'grade' => 'A']);

        $routeParams = ['tenantId' => $sahodaya->id, 'event' => $event->id];

        $csv = $this->actingAs($admin)->get(route('sahodaya.events.results.top-three-winners', $routeParams + ['csv' => '1']));
        $csv->assertOk();
        $csvContent = $csv->streamedContent();
        $this->assertStringContainsString('Student ID', $csvContent);
        $this->assertStringContainsString('1111', $csvContent);
        $this->assertStringNotContainsString('STU/27/1111', $csvContent);

        $docx = $this->actingAs($admin)->get(route('sahodaya.events.results.top-three-winners', $routeParams + ['docx' => '1']));
        $docx->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $docx->headers->get('content-type'),
        );

        $tmp = tempnam(sys_get_temp_dir(), 'docx-test');
        file_put_contents($tmp, $docx->streamedContent());
        $zip = new ZipArchive;
        $zip->open($tmp);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($tmp);

        $this->assertStringContainsString('Student ID', $documentXml);
        $this->assertStringContainsString('1111', $documentXml);
        $this->assertStringNotContainsString('STU/27/1111', $documentXml);
        $this->assertStringContainsString('Winner One', $documentXml);
    }

    public function test_cross_tenant_access_is_forbidden(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodayaA = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Sahodaya A',
            'domain' => 'sahodaya-a.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodayaA->id, 'prefix' => 'SA', 'student_data_mode' => 'counts_only']);

        $sahodayaB = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Sahodaya B',
            'domain' => 'sahodaya-b.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodayaB->id, 'prefix' => 'SB', 'student_data_mode' => 'counts_only']);

        $adminA = User::factory()->create(['tenant_id' => $sahodayaA->id, 'email_verified_at' => now()]);
        $adminA->assignRole('sahodaya_admin');

        $eventB = FestEvent::create([
            'tenant_id' => $sahodayaB->id,
            'title' => 'Event in Sahodaya B',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
        ]);

        $response = $this->actingAs($adminA)->get(route('sahodaya.events.results.top-three-winners', [
            'tenantId' => $sahodayaA->id,
            'event' => $eventB->id,
        ]));

        $response->assertForbidden();
    }
}
