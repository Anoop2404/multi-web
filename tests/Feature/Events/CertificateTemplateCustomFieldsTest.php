<?php

namespace Tests\Feature\Events;

use App\Models\CertificateTemplate;
use App\Models\FestEvent;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Some certificate backgrounds already have their own pre-printed multi-blank layout
 * (e.g. "Master/Miss ___ of class ___" on one line, "from ___" on the next, each at a
 * different position) that the fixed recipient_name/body/certificate_date trio can't
 * address -- body is one flowing paragraph with one position. layout_json.custom_fields
 * adds any number of independently-positioned text fields instead, each running through
 * the same {token} substitution as body (CertificateTemplate::substituteTokens()).
 */
class CertificateTemplateCustomFieldsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User, event: FestEvent} */
    private function makeSahodayaAdminAndEvent(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Custom Fields Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CF', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Custom Fields Event', 'event_type' => 'kalolsavam']);

        return compact('sahodaya', 'admin', 'event');
    }

    public function test_storing_a_template_persists_custom_fields(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAdminAndEvent();

        $response = $this->actingAs($admin)->post(route('sahodaya.certificate-templates.store', [
            'tenantId' => $sahodaya->id,
        ]), [
            'event_type'       => 'fest',
            'event_id'         => $event->id,
            'certificate_type' => 'winner',
            'title'            => 'Multi-Blank Template',
            'is_active'        => true,
            'layout_json'      => [
                'custom_fields' => [
                    ['text' => 'Master/Miss {recipient_name} of class {class}', 'top' => 46, 'left' => 12, 'width' => 76, 'font_size' => 14, 'align' => 'left'],
                    ['text' => 'from {school_name}', 'top' => 52, 'left' => 12, 'width' => 76, 'font_size' => 14, 'align' => 'left'],
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $template = CertificateTemplate::where('title', 'Multi-Blank Template')->firstOrFail();
        $fields = $template->layout_json['custom_fields'];

        $this->assertCount(2, $fields);
        $this->assertSame('Master/Miss {recipient_name} of class {class}', $fields[0]['text']);
        $this->assertSame(46.0, (float) $fields[0]['top']);
        $this->assertSame('from {school_name}', $fields[1]['text']);
        $this->assertSame(52.0, (float) $fields[1]['top']);
    }

    public function test_overlay_layout_normalizes_custom_fields_with_defaults(): void
    {
        ['sahodaya' => $sahodaya, 'event' => $event] = $this->makeSahodayaAdminAndEvent();

        $template = CertificateTemplate::create([
            'tenant_id' => $sahodaya->id, 'event_type' => 'fest', 'event_id' => $event->id,
            'certificate_type' => 'winner', 'title' => 'Layout Test',
            // Deliberately missing font_size/align/etc -- overlayLayout() shouldn't
            // require every key, only merge in what's present.
            'layout_json' => ['custom_fields' => [['text' => '{recipient_name}', 'top' => 40, 'left' => 20]]],
        ]);

        $layout = $template->overlayLayout();

        $this->assertCount(1, $layout['custom_fields']);
        $this->assertSame('{recipient_name}', $layout['custom_fields'][0]['text']);
        $this->assertSame(40, $layout['custom_fields'][0]['top']);
        $this->assertSame(20, $layout['custom_fields'][0]['left']);

        // An unset custom_fields key must fall back to an empty list, not error or
        // silently reuse whatever the class default constant happens to be internally.
        $bare = CertificateTemplate::create([
            'tenant_id' => $sahodaya->id, 'event_type' => 'fest', 'event_id' => $event->id,
            'certificate_type' => 'participation', 'title' => 'No Custom Fields',
        ]);
        $this->assertSame([], $bare->overlayLayout()['custom_fields']);
    }

    public function test_substitute_tokens_matches_body_texts_own_escaping_and_bold_wrapping(): void
    {
        $rendered = CertificateTemplate::substituteTokens(
            'Master/Miss {recipient_name} of class {class}',
            ['recipient_name' => 'Anjali <script>', 'class' => 'VIII'],
            boldVariables: true,
        );

        $this->assertSame('Master/Miss <strong>Anjali &lt;script&gt;</strong> of class <strong>VIII</strong>', $rendered);

        $unbolded = CertificateTemplate::substituteTokens(
            'from {school_name}',
            ['school_name' => 'Sample School'],
            boldVariables: false,
        );
        $this->assertSame('from Sample School', $unbolded);
    }

    /**
     * Regression: Laravel's ConvertEmptyStringsToNull middleware turns a deliberately-
     * cleared Body text textarea into a NULL `body` column -- indistinguishable, on its
     * own, from "this template's body was never configured at all". The blade partial
     * used to fall back to CertificateTemplate::defaultFestBody()'s generic sample
     * sentence in both cases, so an admin who placed every value via custom_fields (and
     * left Body text empty on purpose) got that sample sentence rendered on top of
     * their own fields -- confirmed live: {recipient_name} in a custom field landed
     * right on top of the fallback paragraph's own {recipient_name}, in front of a real
     * user watching. The fallback must fire only when both body AND custom_fields are
     * absent -- a real "nothing configured" template, not "using custom_fields instead".
     */
    public function test_empty_body_does_not_fall_back_to_the_sample_sentence_when_custom_fields_exist(): void
    {
        $withCustomFields = new CertificateTemplate([
            'body' => null,
            'title' => 'Merit',
            'layout_json' => ['custom_fields' => [
                ['text' => '{recipient_name}', 'top' => 40, 'left' => 10, 'width' => 30],
            ]],
        ]);

        $html = view('fest.partials.certificate-body', [
            'template' => $withCustomFields,
            'overlayLayout' => $withCustomFields->overlayLayout(),
            'fieldValues' => ['recipient_name' => 'Anjali K'],
            'backgroundUrl' => 'https://example.test/bg.png',
            'isSample' => true,
        ])->render();

        $this->assertStringNotContainsString('We appreciate the participant', $html);
        $this->assertStringContainsString('Anjali K', $html);

        // A template with truly nothing configured (no body, no custom_fields) is the
        // one genuine case the sample sentence should still cover -- otherwise a fresh,
        // never-touched template would render as a blank certificate.
        $bare = new CertificateTemplate(['body' => null, 'title' => 'Merit', 'layout_json' => []]);
        $bareHtml = view('fest.partials.certificate-body', [
            'template' => $bare,
            'overlayLayout' => $bare->overlayLayout(),
            'fieldValues' => ['recipient_name' => 'Anjali K', 'school_name' => 'Sample School', 'achievement_line' => 'won', 'item_title' => 'Solo', 'event_title' => 'Fest', 'sahodaya_name' => 'X', 'event_dates' => 'Jan'],
            'backgroundUrl' => 'https://example.test/bg.png',
            'isSample' => true,
        ])->render();

        $this->assertStringContainsString('We appreciate the participant', $bareHtml);
    }
}
