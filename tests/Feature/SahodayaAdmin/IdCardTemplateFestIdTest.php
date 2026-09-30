<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\IdCardTemplate;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestIdCardService;
use App\Support\TenancyDatabase;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IdCardTemplateFestIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_source_options_include_fest_id_and_inline_variant(): void
    {
        $options = IdCardTemplate::dataSourceOptions();

        $this->assertArrayHasKey('fest_id', $options);
        $this->assertArrayHasKey('roll_no', $options);
        $this->assertArrayHasKey('student_info_inline_fest_id', $options);
        $this->assertArrayHasKey('student_info_inline', $options);
        $this->assertStringContainsString('Fest ID', $options['fest_id']);
        $this->assertStringContainsString('Fest ID', $options['student_info_inline_fest_id']);
    }

    public function test_template_editor_index_provides_fest_id_in_data_source_options(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Fest ID Template Sahodaya',
            'subdomain' => 'fest-id-sahodaya',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $sahodaya->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('sahodaya_admin');

        if (TenancyDatabase::enabled()) {
            TenancyDatabase::initializeForTenant($sahodaya);
        }

        $response = $this->actingAs($admin)->get("/sahodaya-admin/{$sahodaya->id}/id-card-templates");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('dataSourceOptions.fest_id')
            ->has('dataSourceOptions.roll_no')
            ->has('dataSourceOptions.student_info_inline_fest_id')
        );
    }

    public function test_fest_id_card_service_populates_fest_id_and_student_info_inline_fest_id(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Service Fest ID Sahodaya',
            'subdomain' => 'service-fest-id',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Participant School',
            'subdomain' => 'participant-school',
            'is_active' => true,
        ]);

        $schoolClass = \App\Models\SchoolClass::create([
            'tenant_id' => $school->id,
            'name' => 'Class 10',
            'order' => 10,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Kalotsav 2026',
            'event_type' => 'arts',
            'status' => 'published',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Classical Music',
            'participant_type' => 'single',
            'is_enabled' => true,
        ]);

        $student = Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $schoolClass->id,
            'name' => 'Aditi Sharma',
            'roll_number' => 'ROLL-99',
            'gender' => 'female',
        ]);

        $registration = FestRegistration::create([
            'event_id' => $event->id,
            'item_id' => $item->id,
            'school_id' => $school->id,
            'status' => 'approved',
        ]);

        $participant = FestParticipant::create([
            'registration_id' => $registration->id,
            'student_id' => $student->id,
            'participant_role' => 'performer',
            'level_registration_number' => 'KLM-4321',
        ]);

        $service = app(FestIdCardService::class);
        $sections = $service->cardsGroupedByItem($event, ['item_id' => $item->id]);

        $this->assertNotEmpty($sections);
        $cards = $sections[0]['cards'];
        $this->assertNotEmpty($cards);
        $card = $cards[0];

        $this->assertSame('KLM-4321', $card['fest_id']);
        $this->assertSame('ROLL-99', $card['roll_no']);
        $this->assertStringContainsString('FEST ID: KLM-4321', $card['student_info_inline_fest_id']);
        $this->assertStringContainsString('ROLL NO: ROLL-99', $card['student_info_inline']);
    }
}
