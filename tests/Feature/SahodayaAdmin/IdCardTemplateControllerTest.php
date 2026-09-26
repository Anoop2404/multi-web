<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\IdCardTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\IdCardDiePreset;
use App\Support\TenancyDatabase;
use App\Support\TenantStorage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_admin_can_create_an_inactive_ready_made_template_from_the_builder(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Preset Test Sahodaya',
            'subdomain' => 'preset-test',
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

        $directory = "sahodaya/{$sahodaya->id}/id-card-templates";

        try {
            $response = $this->actingAs($admin)->post(
                "/sahodaya-admin/{$sahodaya->id}/id-card-templates/presets/template-3",
            );

            $response->assertRedirect();
            $response->assertSessionHasNoErrors();
            $response->assertSessionHas('success');

            $template = IdCardTemplate::query()
                ->where('tenant_id', $sahodaya->id)
                ->where('title', 'Kalotsav 2026-27 Student ID — Template 3')
                ->firstOrFail();

            $this->assertFalse($template->is_active);
            $this->assertSame(90, $template->card_width_mm);
            $this->assertSame(135, $template->card_height_mm);
            $this->assertTrue(TenantStorage::exists($template->background_path));
            $this->assertSame(
                270,
                collect($template->fields())->firstWhere('key', 'badge_value')['rotation'],
            );
        } finally {
            TenantStorage::disk()->deleteDirectory($directory);
        }
    }

    public function test_admin_can_apply_the_measured_ten_card_die_to_one_template(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => 'b7f9b005-9f08-4833-8c02-8767a440ad01',
            'type' => 'sahodaya',
            'name' => 'Die Test Sahodaya',
            'subdomain' => 'die-test',
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

        $preset = IdCardDiePreset::options()[0];
        $template = IdCardTemplate::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Customer-specific badge die',
            'audience' => 'student',
            'card_width_mm' => $preset['card_width_mm'],
            'card_height_mm' => $preset['card_height_mm'],
            'cards_per_page' => $preset['cards_per_page'],
            'page_width_mm' => $preset['page_width_mm'],
            'page_height_mm' => $preset['page_height_mm'],
            'grid_json' => $preset['grid'],
            'layout_json' => IdCardTemplate::defaultFields(),
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/id-card-templates")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('diePresets.0.key', 'badge-die-10-up-19x13')
                ->where('diePresets.0.grid.cols', 5)
                ->where('diePresets.0.grid.rows', 2));

        $preview = $this->actingAs($admin)->get(
            "/sahodaya-admin/{$sahodaya->id}/id-card-templates/{$template->id}/preview?mode=die",
        );

        $preview->assertOk();
        $preview->assertSee('size: 482.6mm 330.2mm', false);
        $preview->assertSee('left: 16.3mm; top: 25.1mm;', false);
        $preview->assertSee('left: 376.3mm; top: 165.1mm;', false);
        $this->assertSame(10, substr_count($preview->getContent(), 'class="die-card-slot"'));
    }

    public function test_other_sahodayas_do_not_receive_the_customer_specific_die(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Other Sahodaya',
            'subdomain' => 'other-die-test',
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

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/id-card-templates")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('diePresets', []));
    }
}
