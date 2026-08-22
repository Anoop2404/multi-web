<?php

namespace Tests\Feature\Events;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestCertificateService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers two fixes to the Sahodaya Certificates page:
 *  - "Download all (ZIP)" previously zipped raw HTML with no QR code and
 *    site-relative image URLs that only resolve on-site — extracting and opening
 *    a certificate outside the browser showed broken images and no QR. It now
 *    zips real PDFs with every image embedded as a data URI and a QR code set.
 *  - The new "Preview" link reuses the public print view with its print button
 *    suppressed, via the same isSample mechanism the template-preview screens use.
 */
class FestCertificateExportTest extends TestCase
{
    use RefreshDatabase;

    /** 1x1 transparent PNG — enough for is_file()/file_get_contents() to succeed. */
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private function makeSahodaya(): Tenant
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Cert Export Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CE', 'student_data_mode' => 'counts_only']);

        return $sahodaya;
    }

    private function makeSchool(string $sahodayaId): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodayaId,
            'name' => 'Cert Export School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);
    }

    private function makeCertificate(FestEvent $event, FestEventItem $item, string $schoolId, string $certType = 'participation'): Certificate
    {
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schoolId, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => 1,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        return Certificate::create([
            'entity_type'        => FestParticipant::class,
            'entity_id'          => $participant->id,
            'cert_type'          => $certType,
            'verification_uuid' => (string) Str::uuid(),
            'generated_at'      => now(),
        ]);
    }

    public function test_preview_flag_hides_print_button_but_default_print_shows_it(): void
    {
        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Preview Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);
        $certificate = $this->makeCertificate($event, $item, $school->id);

        $default = $this->get(route('certificates.print', $certificate->verification_uuid));
        $default->assertOk();
        $default->assertSee('Print / Save as PDF', false);

        $preview = $this->get(route('certificates.print', $certificate->verification_uuid).'?preview=1');
        $preview->assertOk();
        $preview->assertDontSee('Print / Save as PDF', false);
    }

    public function test_render_context_embeds_logo_as_data_uri_only_when_requested(): void
    {
        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Embed Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);
        $certificate = $this->makeCertificate($event, $item, $school->id, 'participation');

        $relativeLogoPath = 'certificates/export-test-logo-'.Str::uuid().'.png';
        $absoluteLogoPath = storage_path('app/public/'.$relativeLogoPath);
        @mkdir(dirname($absoluteLogoPath), 0755, true);
        file_put_contents($absoluteLogoPath, base64_decode(self::TINY_PNG_BASE64));

        CertificateTemplate::create([
            'tenant_id'        => $sahodaya->id,
            'event_type'       => 'fest',
            'event_id'         => $event->id,
            'item_id'          => null,
            'certificate_type' => 'participation',
            'title'            => 'Embed Test Template',
            'logo_path'        => $relativeLogoPath,
            'is_active'        => true,
        ]);

        try {
            $service = app(FestCertificateService::class);

            $unembedded = $service->renderContext($certificate);
            $this->assertSame('/storage/'.$relativeLogoPath, $unembedded['logoUrl']);

            $templateCache = [];
            $participantsCache = [];
            $embedded = $service->renderContext($certificate, null, $templateCache, $participantsCache, embedAssets: true);
            $this->assertStringStartsWith('data:image/png;base64,', $embedded['logoUrl']);
        } finally {
            @unlink($absoluteLogoPath);
        }
    }

    public function test_download_zip_produces_real_pdfs_with_no_html_entries(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Zip Export Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);

        $this->makeCertificate($event, $item, $school->id, 'participation');
        $this->makeCertificate($event, $item, $school->id, 'winner');

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.download-zip', [
            'tenantId' => $sahodaya->id,
            'event'    => $event->id,
        ]));

        $response->assertOk();

        $zipPath = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertSame(2, $zip->numFiles);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $this->assertStringEndsWith('.pdf', $name);
            $bytes = $zip->getFromIndex($i);
            $this->assertStringStartsWith('%PDF', $bytes);
        }

        $zip->close();
    }

    public function test_print_all_shows_every_certificate_on_its_own_page(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Print All Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);

        $certA = $this->makeCertificate($event, $item, $school->id, 'participation');
        $certB = $this->makeCertificate($event, $item, $school->id, 'winner');

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.print-all', [
            'tenantId' => $sahodaya->id,
            'event'    => $event->id,
        ]));

        $response->assertOk();
        $response->assertSee($certA->verification_uuid);
        $response->assertSee($certB->verification_uuid);
        $this->assertSame(2, substr_count($response->getContent(), 'class="cert-sheet"'));
        // No per-certificate print buttons inside a combined print-all page — only the
        // one toolbar button at the top (isSample:true suppresses the partial's own).
        $this->assertSame(1, substr_count($response->getContent(), 'Print / Save'));
    }

    public function test_plain_option_omits_background_image_from_print_all(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Plain Option Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);
        $certificate = $this->makeCertificate($event, $item, $school->id, 'participation');

        $relativeBgPath = 'certificates/export-test-bg-'.Str::uuid().'.png';
        $absoluteBgPath = storage_path('app/public/'.$relativeBgPath);
        @mkdir(dirname($absoluteBgPath), 0755, true);
        file_put_contents($absoluteBgPath, base64_decode(self::TINY_PNG_BASE64));

        CertificateTemplate::create([
            'tenant_id'        => $sahodaya->id,
            'event_type'       => 'fest',
            'event_id'         => $event->id,
            'item_id'          => null,
            'certificate_type' => 'participation',
            'title'            => 'Plain Option Template',
            'background_path'  => $relativeBgPath,
            'is_active'        => true,
        ]);

        try {
            $routeArgs = ['tenantId' => $sahodaya->id, 'event' => $event->id];

            // Look for the rendered element's class attribute, not the bare substring
            // "has-background" — that also appears unconditionally in every response's
            // static <style> block (".page.has-background { ... }"), so a bare
            // assertDontSee('has-background') could never pass regardless of this fix.
            $withBackground = $this->actingAs($admin)->get(route('sahodaya.events.certificates.print-all', $routeArgs));
            $withBackground->assertOk();
            $withBackground->assertSee('class="page has-background', false);

            $plain = $this->actingAs($admin)->get(route('sahodaya.events.certificates.print-all', $routeArgs).'?plain=1');
            $plain->assertOk();
            $plain->assertDontSee('class="page has-background', false);
            $plain->assertSee($certificate->verification_uuid);
        } finally {
            @unlink($absoluteBgPath);
        }
    }
}
