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

    public function test_each_run_creates_a_fresh_set_of_three_complete_templates(): void
    {
        $tenantId = (string) Str::uuid();
        $assetDirectory = base_path("storage/app/public/sahodaya/{$tenantId}/id-card-templates");

        try {
            $seeder = new MalappuramKalotsavIdCardTemplatesSeeder;
            $seeder->seedForTenant($tenantId);
            $firstRunIds = IdCardTemplate::where('tenant_id', $tenantId)->pluck('id');
            $seeder->seedForTenant($tenantId);

            $templates = IdCardTemplate::where('tenant_id', $tenantId)->orderBy('title')->get();

            $this->assertCount(6, $templates);
            $this->assertCount(3, $firstRunIds);
            $this->assertTrue($firstRunIds->every(fn (int $id) => $templates->contains('id', $id)));
            $this->assertCount(3, $templates->filter(fn (IdCardTemplate $template) => str_ends_with($template->title, '(Copy 2)')));
            $this->assertSame(6, $templates->pluck('background_path')->unique()->count());
            $this->assertSame(1, $templates->where('is_active', true)->count());
            $this->assertTrue($templates->every(fn (IdCardTemplate $template) => $template->cards_per_page === 10));
            $this->assertTrue($templates->every(fn (IdCardTemplate $template) => (float) $template->page_width_mm === 480.06));
            $this->assertTrue($templates->every(fn (IdCardTemplate $template) => (float) $template->page_height_mm === 314.96));
            $this->assertTrue($templates->every(fn (IdCardTemplate $template) => $template->grid_json['cols'] === 5 && $template->grid_json['rows'] === 2));
            $this->assertTrue($templates->every(fn (IdCardTemplate $template) => collect($template->fields())->where('type', 'item_list')->count() === 1));
            $this->assertTrue($templates->every(fn (IdCardTemplate $template) => collect($template->fields())->where('type', 'item_row')->isEmpty()));
            $this->assertTrue($templates->every(fn (IdCardTemplate $template) => is_file(base_path('storage/app/public/'.$template->background_path))));

            $templateThree = $templates->firstWhere('title', 'Kalotsav 2026-27 Student ID — Template 3');
            $this->assertNotNull($templateThree);
            $this->assertSame(90, $templateThree->card_width_mm);
            $this->assertSame(135, $templateThree->card_height_mm);
            $this->assertSame(
                'horizontal',
                collect($templateThree->fields())->firstWhere('key', 'student_info_divider')['orientation'],
            );
        } finally {
            File::deleteDirectory($assetDirectory);
        }
    }
}
