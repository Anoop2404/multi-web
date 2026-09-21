<?php

namespace Tests\Feature\SchoolAdmin;

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

/**
 * Schools/students shouldn't see a judge's raw marks -- the "Score" column on both School
 * Admin results pages (and the Student portal's Fest/Sports results) was replaced with
 * championship Points (FestGradePointService::pointsForMark()), which is what a school
 * actually cares about, not the underlying marks that produced it.
 */
class SchoolResultsShowPointsNotMarksTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, admin: User, event: FestEvent} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Points Not Marks Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'PNM', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Points Not Marks School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $school->id]);
        $admin->assignRole('school_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Points Not Marks Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open', 'results_published' => true,
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Points Student', 'admission_no' => 'PN1']);

        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'main']);

        // Score deliberately implausible as a points value (raw marks, not points) -- if
        // this leaked through unconverted, it would appear verbatim on the page.
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A', 'score' => 97531]);

        return compact('sahodaya', 'school', 'admin', 'event');
    }

    public function test_published_results_page_does_not_leak_the_raw_score(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->get(
            route('school.kalotsav.reports.published-results', ['tenantId' => $f['school']->id, 'event' => $f['event']->id]),
        );

        $response->assertOk();
        $response->assertDontSee('97531');

        $results = $response->getOriginalContent()->getData()['page']['props']['results'];
        $this->assertArrayNotHasKey('score', $results['items'][0]);
        $this->assertArrayHasKey('points', $results['items'][0]);
    }

    public function test_results_summary_page_does_not_leak_the_raw_score(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->get(
            route('school.kalotsav.reports.results-summary', ['tenantId' => $f['school']->id, 'event' => $f['event']->id]),
        );

        $response->assertOk();
        $response->assertDontSee('97531');

        $results = $response->getOriginalContent()->getData()['page']['props']['results'];
        $this->assertArrayNotHasKey('score', $results['items'][0]);
        $this->assertArrayHasKey('points', $results['items'][0]);
        // pointsForMark() re-derives the grade from the (absurdly high) score first --
        // resolves to 'A' (the platform default is now plain A/B/C, no A+, matching
        // fest_mcs_scoring.php) -- then 1st place under DEFAULT_POINTS['A']['1'] = 8 for
        // an individual item.
        $this->assertSame(8, $results['items'][0]['points']);
    }
}
