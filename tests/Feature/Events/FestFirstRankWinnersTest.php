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

class FestFirstRankWinnersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_csv_and_docx_include_the_short_student_id(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'First Rank Test Sahodaya',
            'domain' => 'first-rank-test.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'FRK', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'First Rank School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class 9-B']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav 2027', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Solo Dance', 'item_code' => 'DNC1',
            'participant_type' => 'individual', 'is_enabled' => true,
        ]);

        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'name' => 'Rank One', 'reg_no' => 'STU/27/2222',
        ]);
        $reg = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create([
            'registration_id' => $reg->id, 'student_id' => $student->id,
            'participant_type' => 'student', 'participant_role' => 'performer', 'chest_no' => 301,
        ]);
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'score' => 98, 'grade' => 'A']);

        $routeParams = ['tenantId' => $sahodaya->id, 'event' => $event->id];

        $pdf = $this->actingAs($admin)->get(route('sahodaya.events.results.first-rank-winners', $routeParams));
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');

        $csv = $this->actingAs($admin)->get(route('sahodaya.events.results.first-rank-winners', $routeParams + ['csv' => '1']));
        $csv->assertOk();
        $csvContent = $csv->streamedContent();
        $this->assertStringContainsString('Student ID', $csvContent);
        $this->assertStringContainsString('2222', $csvContent);
        $this->assertStringNotContainsString('STU/27/2222', $csvContent);
        $this->assertStringContainsString('Rank One', $csvContent);

        $docx = $this->actingAs($admin)->get(route('sahodaya.events.results.first-rank-winners', $routeParams + ['docx' => '1']));
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

        $this->assertStringContainsString('2222', $documentXml);
        $this->assertStringNotContainsString('STU/27/2222', $documentXml);
        $this->assertStringContainsString('Rank One', $documentXml);
    }
}
