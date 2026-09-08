<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
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
 * The Chest Number report's on-screen table already showed "Fest ID" (the student's
 * per-event registration number, App\Models\FestParticipant::level_registration_number)
 * next to the chest number, but the PDF print and CSV export left it out even though the
 * underlying row data (chestNumberRows()) already carries it — an oversight, not a missing
 * feature. Confirms both exports now include it.
 */
class FestChestNumberFestIdColumnTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User, event: FestEvent} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Chest Fest ID Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CFI', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Chest Fest ID Event', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Chest Fest ID School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $schoolClass = SchoolClass::firstOrCreate(
            ['tenant_id' => $school->id, 'name' => '8'],
            ['display_order' => 1, 'is_active' => true],
        );
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'admission_number' => 'ADM1', 'reg_no' => 'REG1', 'name' => 'Chest Fest Student', 'status' => 'active',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Chest Fest Item', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id,
            'status' => 'approved', 'submitted_at' => now(),
        ]);
        FestParticipant::create([
            'registration_id' => $registration->id, 'student_id' => $student->id,
            'participant_type' => 'student', 'participant_role' => 'performer',
            'chest_no' => '42', 'order_no' => 3, 'level_registration_number' => 'FEST-7',
        ]);

        return compact('sahodaya', 'admin', 'event');
    }

    public function test_csv_export_includes_a_fest_id_column(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->fixture();

        $response = $this->actingAs($admin)->get(route('sahodaya.events.chest-numbers.csv', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Fest ID', $content);
        $this->assertStringContainsString('FEST-7', $content);
    }

    public function test_pdf_print_view_includes_the_fest_id_column(): void
    {
        ['event' => $event] = $this->fixture();

        // Renders the same Blade view the print() controller action feeds into
        // PdfGenerator::download() — checking the HTML directly, since the generated PDF
        // itself is binary and not practical to assert text against here.
        $html = view('fest.chest-numbers-print', [
            'event' => $event,
            'item' => null,
            'itemCategory' => null,
            'rows' => collect([
                ['chest_no' => '42', 'order_no' => 3, 'fest_id' => 'FEST-7', 'name' => 'Chest Fest Student', 'item' => 'Chest Fest Item', 'category' => null, 'school' => 'Chest Fest ID School'],
            ]),
            'orgName' => 'Chest Fest ID Sahodaya',
            'logoSrc' => null,
        ])->render();

        $this->assertStringContainsString('Fest ID', $html);
        $this->assertStringContainsString('FEST-7', $html);
    }
}
