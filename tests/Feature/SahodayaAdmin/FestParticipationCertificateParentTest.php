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
            ['id', 'uuid', 'cert_type', 'is_stale', 'is_rendered', 'rendered_at', 'student', 'item', 'items', 'mark', 'registration'],
            array_keys((array) $rows[0]),
        );
        $this->assertSame('Two Leg Student', ((array) $rows[0])['student']['name']);
        // One certificate per student: the listing carries every item it prints, across both legs.
        $this->assertEqualsCanonicalizing(['Pencil Drawing', 'Solo Song'], array_column(((array) $rows[0])['items'], 'title'));
        $this->assertSame('PARENT CERT SCHOOL', strtoupper(((array) $rows[0])['registration']['school']['name']));
    }

    public function test_a_school_can_be_manually_ticked_as_downloaded_and_unticked(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $f = $this->fixture();
        app(FestCertificateService::class)->generateParticipationForEvent($f['root']);
        $schoolId = $f['student']->tenant_id;

        $admin = \App\Models\User::factory()->create(['tenant_id' => $f['root']->tenant_id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');
        $params = ['tenantId' => $f['root']->tenant_id, 'event' => $f['root']->id];

        $group = fn () => collect($this->actingAs($admin)->get(route('sahodaya.events.certificates.index', $params))
            ->viewData('page')['props']['participationBySchool'])->firstWhere('school_id', $schoolId);

        $this->assertFalse($group()['downloaded']);

        $this->actingAs($admin)->post(route('sahodaya.events.certificates.school-downloaded', $params), [
            'school_id' => $schoolId, 'cert_type' => 'participation', 'downloaded' => true,
        ])->assertRedirect();
        $this->assertTrue($group()['downloaded']);
        $this->assertNotNull($group()['downloaded_at']);

        // A merit tick for the same school is a separate flag.
        $this->assertSame(1, \App\Models\FestCertificateSchoolMark::count());

        $this->actingAs($admin)->post(route('sahodaya.events.certificates.school-downloaded', $params), [
            'school_id' => $schoolId, 'cert_type' => 'participation', 'downloaded' => false,
        ])->assertRedirect();
        $this->assertFalse($group()['downloaded']);
        $this->assertSame(0, \App\Models\FestCertificateSchoolMark::count());
    }

    public function test_participation_tick_is_shared_between_the_parent_and_its_child_events(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $f = $this->fixture();
        app(FestCertificateService::class)->generateParticipationForEvent($f['root']);
        $schoolId = $f['student']->tenant_id;

        $admin = \App\Models\User::factory()->create(['tenant_id' => $f['root']->tenant_id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');
        $params = fn (FestEvent $e) => ['tenantId' => $e->tenant_id, 'event' => $e->id];
        $ticked = fn (FestEvent $e) => collect($this->actingAs($admin)->get(route('sahodaya.events.certificates.index', $params($e)))
            ->viewData('page')['props']['participationBySchool'])->firstWhere('school_id', $schoolId)['downloaded'] ?? null;

        // Ticked on a leg...
        $this->actingAs($admin)->post(route('sahodaya.events.certificates.school-downloaded', $params($f['leg1'])), [
            'school_id' => $schoolId, 'cert_type' => 'participation', 'downloaded' => true,
        ])->assertRedirect();

        // ...is visible from the parent (a child event no longer lists participation certificates).
        $this->assertTrue($ticked($f['root']));
        $this->assertNull($ticked($f['leg1']));

        // Unticking from the parent clears it everywhere, including the leg's own row.
        $this->actingAs($admin)->post(route('sahodaya.events.certificates.school-downloaded', $params($f['root'])), [
            'school_id' => $schoolId, 'cert_type' => 'participation', 'downloaded' => false,
        ])->assertRedirect();
        $this->assertFalse($ticked($f['root']));
        $this->assertSame(0, \App\Models\FestCertificateSchoolMark::count());
    }
    public function test_print_complete_prints_only_students_with_all_results_published_once_and_reports_the_rest(): void
    {
        config(['services.pdf_converter.url' => 'https://pdf.example.test/generate-pdf']);
        \Illuminate\Support\Facades\Http::fake(['pdf.example.test/*' => \Illuminate\Support\Facades\Http::response('%PDF-1.4 fake', 200)]);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $f = $this->fixture();
        $school = \App\Models\Tenant::find($f['student']->tenant_id);
        $class = $f['student']->schoolClass ?? \App\Models\SchoolClass::where('tenant_id', $school->id)->first();

        // A second student of the same school whose only item has NOT published results yet.
        $lateItem = FestEventItem::create(['event_id' => $f['leg1']->id, 'title' => 'Late Item', 'participant_type' => 'individual', 'is_enabled' => true]);
        $late = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Late Student', 'status' => 'active']);
        $reg = FestRegistration::create(['event_id' => $f['leg1']->id, 'item_id' => $lateItem->id, 'school_id' => $school->id, 'status' => 'approved']);
        FestParticipant::create(['registration_id' => $reg->id, 'student_id' => $late->id, 'participant_type' => 'student', 'participant_role' => 'performer', 'chest_no' => 2]);

        app(FestCertificateService::class)->generateParticipationForEvent($f['root']);

        $admin = \App\Models\User::factory()->create(['tenant_id' => $f['root']->tenant_id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');
        $base = "/sahodaya-admin/{$f['root']->tenant_id}/events/{$f['root']->id}/certificates";

        // Nothing published yet: nobody is complete.
        $this->actingAs($admin)->postJson("{$base}/print-complete", [])->assertStatus(422);

        // Publish the two items of the first student only.
        FestEventItem::whereIn('title', ['Pencil Drawing', 'Solo Song'])->update(['results_published_at' => now()]);

        $run = $this->actingAs($admin)->postJson("{$base}/print-complete", ['school_id' => $school->id]);
        $run->assertOk()->assertJsonPath('count', 1)->assertJsonPath('schools', 1);
        // Both print variants of the run are offered; the plain one drops the background.
        $this->assertStringContainsString('&plain=1', $run->json('print_url_plain'));
        $this->assertStringNotContainsString('plain', $run->json('print_url_with_background'));
        $this->actingAs($admin)->get($run->json('print_url_plain'))->assertOk();
        $this->assertSame(1, \App\Models\FestCertificatePrint::count());
        $this->assertSame(0, \App\Models\FestCertificateSchoolMark::count(), 'a school with a pending student is not auto-ticked');

        // Same again: the printed student is not printed twice.
        $this->actingAs($admin)->postJson("{$base}/print-complete", [])->assertStatus(422);

        // The print page for the run renders exactly that student's certificate.
        $this->actingAs($admin)->get($run->json('print_url'))->assertOk();

        // The report lists the printed student and, separately, the pending one with the awaited item.
        $this->actingAs($admin)->get($run->json('report_url').'&preview=1')->assertOk();
        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            $html = (string) ($request->data()['html'] ?? '');

            return str_contains($html, 'Two Leg Student') && str_contains($html, 'Late Student') && str_contains($html, 'Late Item');
        });

        // Once the late item publishes too, the last student prints and the school auto-ticks.
        $lateItem->update(['results_published_at' => now()]);
        $this->actingAs($admin)->postJson("{$base}/print-complete", [])->assertOk()->assertJsonPath('count', 1);
        $this->assertSame(1, \App\Models\FestCertificateSchoolMark::where('cert_type', 'participation')->count());
    }
    public function test_a_student_with_two_certificate_rows_is_listed_and_exported_once_and_the_command_cleans_up(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $f = $this->fixture();
        $service = app(FestCertificateService::class);
        $service->generateParticipationForEvent($f['root']);

        // Leftover: a second participation certificate for the same student, anchored on the
        // other leg's participant row.
        $other = FestParticipant::where('student_id', $f['student']->id)->orderByDesc('id')->first();
        Certificate::create(['entity_type' => FestParticipant::class, 'entity_id' => $other->id, 'cert_type' => 'participation', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now()]);
        $this->assertSame(2, Certificate::where('cert_type', 'participation')->count());

        $admin = \App\Models\User::factory()->create(['tenant_id' => $f['root']->tenant_id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');
        $rows = $this->actingAs($admin)->get(route('sahodaya.events.certificates.index', ['tenantId' => $f['root']->tenant_id, 'event' => $f['root']->id]))
            ->viewData('page')['props']['certificates'];
        $this->assertCount(1, $rows, 'listed once');
        $this->assertCount(1, $service->exportScope($f['root'], certType: 'participation')[0], 'exported/printed once');

        $this->artisan('fest:participation-duplicates', ['event' => $f['root']->id])->assertSuccessful();
        $this->assertSame(2, Certificate::where('cert_type', 'participation')->count(), 'report only, nothing removed');

        $this->artisan('fest:participation-duplicates', ['event' => $f['root']->id, '--fix' => true])->assertSuccessful();
        $left = Certificate::where('cert_type', 'participation')->get();
        $this->assertCount(1, $left);
        $this->assertSame((int) FestParticipant::where('student_id', $f['student']->id)->min('id'), (int) $left->first()->entity_id, 'the lowest-anchored certificate is the one kept');
    }
    public function test_generate_reuses_an_existing_certificate_for_a_student_instead_of_creating_a_second(): void
    {
        $f = $this->fixture();
        $service = app(FestCertificateService::class);
        $service->generateParticipationForEvent($f['root']);
        $original = Certificate::where('cert_type', 'participation')->sole();

        // Simulate the anchor having shifted: the only certificate now sits on the student's
        // other participant row (the higher id).
        $other = FestParticipant::where('student_id', $f['student']->id)->orderByDesc('id')->first();
        $original->update(['entity_id' => $other->id]);

        $service->generateParticipationForEvent($f['root']);
        $service->generateParticipationForEvent($f['leg1']);
        $service->generateParticipationForEvent($f['root']);

        $certs = Certificate::where('cert_type', 'participation')->get();
        $this->assertCount(1, $certs, 'still one certificate per student');
        $this->assertSame($original->verification_uuid, $certs->first()->verification_uuid, 'the existing certificate (and its QR) was kept, not replaced');
        $this->assertSame(FestParticipant::where('student_id', $f['student']->id)->min('id'), (int) $certs->first()->entity_id, 're-anchored on the current anchor row');
    }

    public function test_child_events_hold_participation_certificates_back(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $f = $this->fixture();
        $service = app(FestCertificateService::class);
        $service->generateParticipationForEvent($f['root']);
        $this->assertTrue($service->holdsParticipation($f['leg1']));
        $this->assertFalse($service->holdsParticipation($f['root']));

        $admin = \App\Models\User::factory()->create(['tenant_id' => $f['root']->tenant_id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');
        $tenant = $f['root']->tenant_id;

        // The leg's certificates page lists no participation certificates and says so.
        $page = $this->actingAs($admin)->get(route('sahodaya.events.certificates.index', ['tenantId' => $tenant, 'event' => $f['leg1']->id]));
        $props = $page->viewData('page')['props'];
        $this->assertTrue($props['participationHeld']);
        $this->assertStringContainsString("/events/{$f['root']->id}/certificates/participants", $props['participationParentUrl']);
        $this->assertSame([], collect($props['certificates'])->where('cert_type', 'participation')->values()->all());
        $this->assertSame([], (array) $props['participationBySchool']);

        // Exports from the leg skip them too; the parent still has it.
        $this->assertCount(0, $service->exportScope($f['leg1'], certType: 'participation')[0]);
        $this->assertCount(1, $service->exportScope($f['root'], certType: 'participation')[0]);

        // Generating participation from a leg is refused; the parent's workspace is where it lives.
        $before = Certificate::where('cert_type', 'participation')->count();
        $this->actingAs($admin)->post(route('sahodaya.events.certificates.participation', ['tenantId' => $tenant, 'event' => $f['leg1']->id]))
            ->assertSessionHas('error');
        $this->assertSame($before, Certificate::where('cert_type', 'participation')->count());
        $this->actingAs($admin)->get("/sahodaya-admin/{$tenant}/events/{$f['leg1']->id}/certificates/participants")
            ->assertRedirect("/sahodaya-admin/{$tenant}/events/{$f['root']->id}/certificates/participants");
    }
    public function test_print_status_report_lists_every_student_by_school_with_printed_ready_and_awaiting(): void
    {
        config(['services.pdf_converter.url' => 'https://pdf.example.test/generate-pdf']);
        \Illuminate\Support\Facades\Http::fake(['pdf.example.test/*' => \Illuminate\Support\Facades\Http::response('%PDF-1.4 fake', 200)]);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $f = $this->fixture();
        $school = \App\Models\Tenant::find($f['student']->tenant_id);
        $class = \App\Models\SchoolClass::where('tenant_id', $school->id)->first();

        $lateItem = FestEventItem::create(['event_id' => $f['leg1']->id, 'title' => 'Late Item', 'participant_type' => 'individual', 'is_enabled' => true]);
        $late = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Late Student', 'status' => 'active']);
        $reg = FestRegistration::create(['event_id' => $f['leg1']->id, 'item_id' => $lateItem->id, 'school_id' => $school->id, 'status' => 'approved']);
        FestParticipant::create(['registration_id' => $reg->id, 'student_id' => $late->id, 'participant_type' => 'student', 'participant_role' => 'performer', 'chest_no' => 2]);
        app(FestCertificateService::class)->generateParticipationForEvent($f['root']);

        // The fest's own category scheme: classes 8, 9 & 10 are Category 3 -> C3.
        $f['root']->update(['fee_settings' => array_merge((array) $f['root']->fee_settings, ['class_group_scheme' => 'custom'])]);
        \App\Models\FestEventClassGroup::create(['tenant_id' => $f['root']->tenant_id, 'event_id' => $f['root']->id, 'key' => 'category_3', 'label' => 'Category 3', 'classes' => [8, 9, 10], 'sort_order' => 3]);

        FestEventItem::whereIn('title', ['Pencil Drawing', 'Solo Song'])->update(['results_published_at' => now()]);

        $admin = \App\Models\User::factory()->create(['tenant_id' => $f['root']->tenant_id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');
        $base = "/sahodaya-admin/{$f['root']->tenant_id}/events/{$f['root']->id}/certificates/print-status";

        // Before printing: one student ready, one awaiting.
        $xml = $this->actingAs($admin)->get("{$base}/xls")->streamedContent();
        $this->assertStringContainsString('Two Leg Student', $xml);
        $this->assertStringContainsString('Late Student', $xml);
        foreach (['Sl No', 'Student', 'Fest ID', 'Category', 'Items', 'Complete', 'Verification'] as $column) {
            $this->assertStringContainsString(">{$column}<", $xml);
        }
        $this->assertStringNotContainsString('Class', $xml, 'no class column');
        $this->assertStringContainsString('Pencil Drawing', $xml);
        $this->assertStringContainsString('>C3<', $xml, 'category is derived from the class (Category 3 -> C3)');
        $this->assertStringContainsString('>Complete<', $xml, 'a student with every item published is marked Complete');

        // After printing the complete student, the "not printed" filter keeps only the other one.
        $this->actingAs($admin)->postJson(str_replace('print-status', 'print-complete', $base), [])->assertOk();
        // In the full list the printed student comes first, the rest after.
        $all = $this->actingAs($admin)->get("{$base}/xls")->streamedContent();
        $this->assertLessThan(strpos($all, 'Late Student'), strpos($all, 'Two Leg Student'), 'printed students are listed first');
        $unprinted = $this->actingAs($admin)->get("{$base}/xls?status=unprinted")->streamedContent();
        $this->assertStringContainsString('Late Student', $unprinted);
        $this->assertStringNotContainsString('Two Leg Student', $unprinted);
        $printed = $this->actingAs($admin)->get("{$base}/xls?status=printed")->streamedContent();
        $this->assertStringContainsString('Two Leg Student', $printed);
        $this->assertStringNotContainsString('Late Student', $printed);

        $this->actingAs($admin)->get("{$base}/pdf?preview=1&status=all")->assertOk();
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains((string) ($r->data()['html'] ?? ''), 'Late Student') && str_contains((string) $r->data()['html'], 'Two Leg Student'));
    }
}
