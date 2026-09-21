<?php

namespace Tests\Unit\Seeders;

use App\Models\IdCardTemplate;
use Database\Seeders\MalappuramKalotsavIdCardTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class MalappuramKalotsavIdCardTemplatesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_idempotently_seeds_both_complete_templates(): void
    {
        $tenantId = (string) Str::uuid();
        $assetDirectory = base_path("storage/app/public/sahodaya/{$tenantId}/id-card-templates");

        try {
            $seeder = new MalappuramKalotsavIdCardTemplatesSeeder;
            $seeder->seedForTenant($tenantId);
            $seeder->seedForTenant($tenantId);

            $templates = IdCardTemplate::where('tenant_id', $tenantId)->orderBy('title')->get();

            $this->assertCount(2, $templates);
            $this->assertTrue($templates->every(fn (IdCardTemplate $template) => $template->cards_per_page === 10));
            $this->assertTrue($templates->every(fn (IdCardTemplate $template) => (float) $template->page_width_mm === 480.06));
            $this->assertTrue($templates->every(fn (IdCardTemplate $template) => (float) $template->page_height_mm === 314.96));
            $this->assertTrue($templates->every(fn (IdCardTemplate $template) => $template->grid_json['cols'] === 5 && $template->grid_json['rows'] === 2));
            $this->assertTrue($templates->every(fn (IdCardTemplate $template) => collect($template->fields())->where('type', 'item_list')->count() === 1));
            $this->assertTrue($templates->every(fn (IdCardTemplate $template) => collect($template->fields())->where('type', 'item_row')->isEmpty()));
            $this->assertTrue($templates->every(fn (IdCardTemplate $template) => is_file(base_path('storage/app/public/'.$template->background_path))));
        } finally {
            File::deleteDirectory($assetDirectory);
        }
    }
}
