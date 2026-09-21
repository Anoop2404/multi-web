<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\IdCardTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenancyDatabase;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdCardTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_seeded_dynamic_fields_and_clear_nullable_template_settings(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Template Test Sahodaya',
            'subdomain' => 'template-test',
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

        $template = IdCardTemplate::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Editable template',
            'audience' => 'student',
            'card_width_mm' => 89,
            'card_height_mm' => 135,
            'cards_per_page' => 10,
            'page_width_mm' => 480.06,
            'page_height_mm' => 314.96,
            'layout_json' => [],
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(
            "/sahodaya-admin/{$sahodaya->id}/id-card-templates/{$template->id}",
            [
                'title' => null,
                'audience' => null,
                'card_width_mm' => 89,
                'card_height_mm' => 135,
                'cards_per_page' => 10,
                'page_width_mm' => null,
                'page_height_mm' => null,
                'fields' => [[
                    'key' => 'student_info_inline',
                    'type' => 'text',
                    'source' => 'student_info_inline',
                    'text_format' => 'CATEGORY: {category} | ROLL NO: {roll_no} | GENDER: {gender_upper}',
                    'top' => 78,
                    'left' => 4.5,
                    'width' => 91,
                    'height' => 3,
                    'font_size' => 10,
                    'line_height' => 1.08,
                    'font_family' => 'Arial',
                    'font_weight' => 'bold',
                    'align' => 'center',
                    'color' => '#ffffff',
                    'wrap' => false,
                ]],
                'is_active' => true,
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $template->refresh();
        $this->assertNull($template->title);
        $this->assertNull($template->audience);
        $this->assertNull($template->page_width_mm);
        $this->assertNull($template->page_height_mm);
        $this->assertSame(1.08, $template->layout_json[0]['line_height']);
        $this->assertSame(
            'CATEGORY: {category} | ROLL NO: {roll_no} | GENDER: {gender_upper}',
            $template->layout_json[0]['text_format'],
        );
    }
}
