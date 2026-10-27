<?php

namespace Database\Seeders;

use App\Models\McqExam;
use App\Models\McqHallTicketTemplate;
use App\Models\Tenant;
use App\Support\Mcq\McqHallTicketDesign;
use App\Support\TenancyDatabase;
use App\Support\TenantStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KannurMcqHallTicketTemplateSeeder extends Seeder
{
    public function run(?string $tenantId = null, ?int $examId = null): void
    {
        $tenantId ??= tenant('id') ?? env('SAHODAYA_UUID', '71fcc096-e12e-4eb3-bc45-ecbc9c4ab306');
        $tenant = Tenant::where('type', 'sahodaya')->findOrFail($tenantId);
        $examId ??= (int) env('MCQ_EXAM_ID', 1);

        TenancyDatabase::withTenantDatabase($tenant, function () use ($tenant, $examId) {
            $exam = McqExam::where('tenant_id', $tenant->id)->findOrFail($examId);
            $base = "mcq-hall-tickets/{$tenant->id}/{$exam->id}/kannur";
            $disk = TenantStorage::uploadDisk();
            foreach (['admit-card-background.png', 'controller-signature.jpg'] as $asset) {
                $bytes = file_get_contents(resource_path('mcq-templates/kannur/'.$asset));
                if (! Storage::disk($disk)->put("{$base}/{$asset}", $bytes, ['visibility' => 'private'])) {
                    throw new \RuntimeException("Could not store {$asset}.");
                }
            }
            $design = McqHallTicketDesign::normalize(array_merge(
                require resource_path('mcq-templates/kannur/admit-card.php'),
                ['background_path' => "{$base}/admit-card-background.png", 'controller_signature_path' => "{$base}/controller-signature.jpg"]
            ));
            DB::transaction(function () use ($tenant, $exam, $design) {
                $template = McqHallTicketTemplate::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'title' => 'Kannur Sahodaya STSE — supplied admit card'],
                    ['design_json' => $design, 'is_active' => true, 'is_default' => false]
                );
                $settings = McqHallTicketDesign::mergeIntoSettings($exam->settings_json ?? [], $design);
                $settings['hall_ticket_design_scope'] = 'exam';
                $exam->update(['hall_ticket_template_id' => $template->id, 'settings_json' => $settings]);
            });
            $this->command?->info("Kannur admit-card template applied to {$tenant->name}, exam #{$exam->id}. Original PDF wording preserved.");
        });
    }
}
