<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Region;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Participation certificates for a phased/regional event (the MCS Kalotsav shape: one root
 * event, several phase+region legs) are issued once per person from the PARENT event --
 * not once per leg -- and read the parent's title and the person's items from every leg.
 */
class FestParticipationCertificateParentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{root: FestEvent, leg1: FestEvent, leg2: FestEvent, student: Student} */
    private function fixture(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Parent Cert Sahodaya',
            'domain' => 'parent-cert-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Parent Cert School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Two Leg Student', 'status' => 'active']);

        $regionA = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'RGA']);
        $regionB = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region B', 'code' => 'RGB']);

        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Parent Cert Kalotsavam', 'event_type' => 'kalolsavam',
            'workflow_mode' => 'phased_regional_billing', 'status' => 'published',
        ]);
        $phase1 = FestEventPhase::create(['event_id' => $root->id, 'name' => 'Off Stage', 'code' => 'off-stage', 'is_regional' => true]);
        $phase2 = FestEventPhase::create(['event_id' => $root->id, 'name' => 'On Stage', 'code' => 'on-stage', 'is_regional' => true]);

        $legs = [];
        foreach ([[$phase1, $regionA, 'Off Stage — Region A', 'Pencil Drawing'], [$phase2, $regionB, 'On Stage — Region B', 'Solo Song']] as [$phase, $region, $title, $itemTitle]) {
            $leg = FestEvent::create([
                'tenant_id' => $sahodaya->id, 'parent_event_id' => $root->id, 'root_event_id' => $root->id,
                'source_phase_id' => $phase->id, 'region_id' => $region->id, 'title' => $title,
                'event_type' => 'kalolsavam', 'workflow_mode' => 'phased_regional_billing', 'status' => 'published',
                // Deliberately NOT results_published: participation must not wait for it.
                'results_published' => false,
            ]);
            $item = FestEventItem::create(['event_id' => $leg->id, 'title' => $itemTitle, 'participant_type' => 'individual', 'is_enabled' => true]);
            $reg = FestRegistration::create(['event_id' => $leg->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
            FestParticipant::create(['registration_id' => $reg->id, 'student_id' => $student->id, 'participant_type' => 'student', 'participant_role' => 'performer', 'chest_no' => 1]);
            $legs[] = $leg;
        }

        return ['root' => $root, 'leg1' => $legs[0], 'leg2' => $legs[1], 'student' => $student];
    }

    public function test_generating_from_a_leg_issues_one_certificate_per_student_from_the_parent(): void
    {
        $f = $this->fixture();
        $service = app(FestCertificateService::class);

        $fromLeg = $service->generateParticipationForEvent($f['leg1']);
        $this->assertCount(1, $fromLeg, 'One student in two legs must get one certificate, not one per leg.');

        // Same again from the other leg and from the parent itself -- still just one.
        $service->generateParticipationForEvent($f['leg2']);
        $service->generateParticipationForEvent($f['root']);

        $this->assertSame(1, Certificate::where('cert_type', 'participation')->count());
    }

    public function test_the_certificate_reads_the_parent_title_and_lists_items_from_every_leg(): void
    {
        $f = $this->fixture();
        $service = app(FestCertificateService::class);

        $cert = $service->generateParticipationForEvent($f['leg1'])[0];
        $payload = $service->payloadFor($cert);

        $this->assertSame($f['root']->id, $payload['event']->id);
        $this->assertSame('Parent Cert Kalotsavam', $payload['event']->title);

        $fields = $service->renderContext($cert, $payload)['fieldValues'];
        $this->assertSame('Parent Cert Kalotsavam', $fields['event_title']);
        $this->assertEqualsCanonicalizing(['Pencil Drawing', 'Solo Song'], $fields['item_titles']);
    }

    public function test_seeding_command_creates_one_active_participation_template_on_the_parent(): void
    {
        $f = $this->fixture();

        // Pointed at a leg, it still lands on the parent.
        $this->artisan('fest:seed-participation-template', ['event' => $f['leg1']->id])->assertSuccessful();

        $template = \App\Models\CertificateTemplate::where('certificate_type', 'participation')->sole();
        $this->assertSame($f['root']->id, $template->event_id);
        $this->assertTrue($template->is_active);
        $this->assertStringContainsString('{participation_items_box}', $template->body);

        // A second run won't silently replace it...
        $this->artisan('fest:seed-participation-template', ['event' => $f['root']->id])->assertFailed();
        // ...unless forced, which leaves exactly one active.
        $this->artisan('fest:seed-participation-template', ['event' => $f['root']->id, '--force' => true])->assertSuccessful();
        $this->assertSame(1, \App\Models\CertificateTemplate::where('certificate_type', 'participation')->where('is_active', true)->count());

        $service = app(FestCertificateService::class);
        $this->assertSame($template->tenant_id, $service->resolveTemplate($f['root'], null, 'participation')->tenant_id);
    }

    /**
     * The list pages used to serialize each certificate's full payloadFor() shape (whole
     * models plus their loaded relations) into the Inertia page -- ~30 KB per certificate,
     * ~98 MB for a hub of thousands, which exhausted memory in production. Only the fields
     * the pages actually read may be sent.
     */
    public function test_the_certificates_page_sends_only_the_slim_fields_the_page_reads(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $f = $this->fixture();
        app(FestCertificateService::class)->generateParticipationForEvent($f['root']);

        $admin = \App\Models\User::factory()->create(['tenant_id' => $f['root']->tenant_id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.index', [
            'tenantId' => $f['root']->tenant_id, 'event' => $f['root']->id,
        ]));
        $response->assertOk();

        $rows = $response->viewData('page')['props']['certificates'];
        $this->assertCount(1, $rows);
        $this->assertEqualsCanonicalizing(
            ['id', 'uuid', 'cert_type', 'is_stale', 'is_rendered', 'rendered_at', 'student', 'item', 'mark', 'registration'],
            array_keys((array) $rows[0]),
        );
        $this->assertSame('Two Leg Student', ((array) $rows[0])['student']['name']);
        $this->assertSame('PARENT CERT SCHOOL', strtoupper(((array) $rows[0])['registration']['school']['name']));
    }
}
