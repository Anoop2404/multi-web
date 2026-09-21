<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestIdCardService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestIdCardPhotoRouteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;
    private Tenant $school;
    private User $sahodayaAdmin;
    private FestEvent $event;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Kochi Metro Sahodaya',
            'domain' => 'sahodaya-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        $this->school = Tenant::create([
            'id' => (string) Str::uuid(),
            'parent_id' => $this->sahodaya->id,
            'type' => 'school',
            'name' => 'Alameen International Public School',
            'domain' => 'school-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $this->school->id,
            'name' => '10-A',
            'display_order' => 1,
        ]);

        $this->sahodayaAdmin = User::factory()->create([
            'tenant_id' => $this->sahodaya->id,
            'email_verified_at' => now(),
        ]);
        $this->sahodayaAdmin->assignRole('sahodaya_admin');

        $this->event = FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Kalotsav 2026-27',
            'event_type' => 'kalotsavam',
            'status' => 'published',
            'conduct_mode' => 'centralized',
            'academic_year' => '2026-27',
        ]);

        $item = FestEventItem::create([
            'event_id' => $this->event->id,
            'title' => 'Recitation - Malayalam',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);

        $this->student = Student::create([
            'tenant_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => 'MUHAMMAD ZAYAN A. F',
            'gender' => 'male',
            'dob' => '2012-05-10',
            'photo' => 'students/'.$this->school->id.'/zayan.jpg',
        ]);

        $reg = FestRegistration::create([
            'event_id' => $this->event->id,
            'school_id' => $this->school->id,
            'item_id' => $item->id,
            'status' => 'approved',
        ]);

        FestParticipant::create([
            'registration_id' => $reg->id,
            'student_id' => $this->student->id,
            'participant_role' => 'lead',
        ]);
    }

    public function test_cards_json_returns_sahodaya_photo_url_in_sahodaya_admin_context(): void
    {
        $service = app(FestIdCardService::class);
        $cards = $service->cards($this->event, 'student', ['scope' => 'event']);

        $this->assertNotEmpty($cards);
        $card = $cards[0];
        $this->assertEquals('MUHAMMAD ZAYAN A. F', $card['name']);
        $this->assertNotNull($card['photo_url']);

        // Must point to sahodaya-admin photo route, NOT school-admin
        $this->assertStringContainsString("/sahodaya-admin/{$this->sahodaya->id}/students/{$this->student->id}/photo", $card['photo_url']);
        $this->assertStringNotContainsString("/school-admin/", $card['photo_url']);
    }

    public function test_cards_json_endpoint_returns_sahodaya_photo_url(): void
    {
        $response = $this->actingAs($this->sahodayaAdmin)->getJson(
            "/sahodaya-admin/{$this->sahodaya->id}/events/{$this->event->id}/id-cards/cards?audience=student&scope=event"
        );

        $response->assertOk();
        $cards = $response->json('cards');
        $this->assertNotEmpty($cards);
        $this->assertStringContainsString(
            "/sahodaya-admin/{$this->sahodaya->id}/students/{$this->student->id}/photo",
            $cards[0]['photo_url']
        );
    }

    public function test_sahodaya_admin_is_authorized_for_governed_school_admin_routes(): void
    {
        $response = $this->actingAs($this->sahodayaAdmin)->get(
            "/school-admin/{$this->school->id}/students/{$this->student->id}/photo"
        );

        // Should not be 403 (Forbidden)
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_sahodaya_admin_cannot_access_unrelated_school(): void
    {
        $otherSahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Other Sahodaya',
            'is_active' => true,
        ]);

        $unrelatedSchool = Tenant::create([
            'id' => (string) Str::uuid(),
            'parent_id' => $otherSahodaya->id,
            'type' => 'school',
            'name' => 'Unrelated School',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->sahodayaAdmin)->get(
            "/school-admin/{$unrelatedSchool->id}/students/{$this->student->id}/photo"
        );

        $response->assertStatus(403);
    }

    public function test_pdf_all_schools_generates_pdf(): void
    {
        $response = $this->actingAs($this->sahodayaAdmin)->get(
            "/sahodaya-admin/{$this->sahodaya->id}/events/{$this->event->id}/id-cards/pdf-all-schools?template=pass&scope=event"
        );

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_die_generator_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->sahodayaAdmin)->get(
            "/sahodaya-admin/{$this->sahodaya->id}/events/{$this->event->id}/id-cards/die"
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Sahodaya/Events/IdCards/DieGenerator', false)
            ->has('schools')
            ->has('volumes')
            ->has('totalParticipants')
        );
    }

    public function test_die_generator_renders_with_active_custom_template(): void
    {
        \App\Models\IdCardTemplate::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $this->event->id,
            'title' => 'Die Cut 4-up',
            'audience' => 'student',
            'is_active' => true,
            'background_path' => 'templates/die-bg.jpg',
            'card_width_mm' => 96,
            'card_height_mm' => 72,
            'page_width_mm' => 297,
            'page_height_mm' => 210,
            'grid_json' => [
                'cols' => 2,
                'rows' => 2,
                'left_margin_mm' => 10,
                'top_margin_mm' => 10,
                'col_pitch_mm' => 140,
                'row_pitch_mm' => 95,
            ],
            'fields_json' => [],
        ]);

        $response = $this->actingAs($this->sahodayaAdmin)->get(
            "/sahodaya-admin/{$this->sahodaya->id}/events/{$this->event->id}/id-cards/die"
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Sahodaya/Events/IdCards/DieGenerator', false)
            ->where('activeTemplate.name', 'Die Cut 4-up')
        );
    }

    public function test_pdf_die_downloads_single_school_pdf(): void
    {
        $response = $this->actingAs($this->sahodayaAdmin)->get(
            "/sahodaya-admin/{$this->sahodaya->id}/events/{$this->event->id}/id-cards/die/pdf?school_id={$this->school->id}"
        );

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_pdf_die_downloads_all_schools_pdf(): void
    {
        $response = $this->actingAs($this->sahodayaAdmin)->get(
            "/sahodaya-admin/{$this->sahodaya->id}/events/{$this->event->id}/id-cards/die/pdf"
        );

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }
}
