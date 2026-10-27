<?php

namespace Tests\Feature;

use App\Models\McqExam;
use App\Models\McqHallTicketTemplate;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Mcq\McqHallTicketDesign;
use Database\Seeders\KannurMcqHallTicketTemplateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class KannurMcqHallTicketTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_kannur_template_can_be_applied_repeatedly_without_changing_exam_schedule(): void
    {
        Storage::fake('shared');
        config(['filesystems.upload_disk' => 'shared']);
        $owner = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Kannur Sahodaya', 'is_active' => true]);
        $exam = McqExam::create(['tenant_id' => $owner->id, 'title' => 'KANNUR SAHODAYA TALENT SEARCH EXAM 2026', 'status' => 'published',
            'scheduled_at' => '2026-11-01 10:00:00', 'duration_minutes' => 120, 'next_hall_ticket_no' => 100,
            'settings_json' => ['class_schedules' => ['1' => ['duration_minutes' => 90]]]]);
        $seeder = new KannurMcqHallTicketTemplateSeeder;
        $seeder->run($owner->id, $exam->id);
        $seeder->run($owner->id, $exam->id);
        $exam->refresh();
        $this->assertSame(1, McqHallTicketTemplate::where('tenant_id', $owner->id)->count());
        $this->assertFalse(McqHallTicketTemplate::first()->is_default);
        $design = McqHallTicketDesign::fromExam($exam);
        $this->assertSame('custom_background', $design['layout']);
        $this->assertFalse($design['show_exam_date']);
        $this->assertSame(1, McqHallTicketDesign::printGeometry($design)['per_page']);
        $this->assertSame('gender', $design['custom_fields'][1]['source']);
        $this->assertSame('2026-11-01 10:00:00', $exam->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame(120, $exam->duration_minutes);
        $this->assertSame(90, $exam->settings_json['class_schedules'][1]['duration_minutes']);
        $this->assertSame(file_get_contents(resource_path('mcq-templates/kannur/admit-card-background.png')), Storage::disk('shared')->get($design['background_path']));
        Storage::disk('shared')->assertExists($design['controller_signature_path']);

        $this->seed(RolesAndPermissionsSeeder::class);
        SahodayaProfile::create(['tenant_id' => $owner->id, 'prefix' => 'KN', 'student_data_mode' => 'counts_only']);
        $admin = User::factory()->create(['tenant_id' => $owner->id]);
        $admin->assignRole('sahodaya_admin');
        $url = "/sahodaya-admin/{$owner->id}/mcq-exams/{$exam->id}/hall-tickets/design";
        $this->actingAs($admin)->post($url, [
            'controller_signature' => new UploadedFile(resource_path('mcq-templates/kannur/controller-signature.jpg'), 'signature.jpg', 'image/jpeg', null, true),
            'show_controller_sig' => 1,
            'field_positions' => ['controller_signature' => ['x' => 15, 'y' => 37, 'width' => 20, 'height' => 3]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $updated = McqHallTicketDesign::fromExam($exam->fresh());
        $this->assertSame(15.0, $updated['field_positions']['controller_signature']['x']);
        Storage::disk('shared')->assertExists($updated['controller_signature_path']);
        $this->post($url, ['remove_controller_signature' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(McqHallTicketDesign::fromExam($exam->fresh())['controller_signature_path']);
    }
}
