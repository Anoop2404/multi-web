<?php

namespace App\Jobs;

use App\Models\McqAdmitCardExport;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Support\Mcq\McqHallTicketDesign;
use App\Support\PdfGenerator;
use App\Support\TenancyDatabase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateMcqAdmitCardExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(public string $tenantId, public int $exportId) {}

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        TenancyDatabase::withTenantDatabase($tenant, function () {
            $export = McqAdmitCardExport::where('tenant_id', $this->tenantId)->findOrFail($this->exportId);
            if ($export->status === 'completed') {
                return;
            }
            $export->update(['status' => 'processing']);
            try {
                $exam = McqExam::where('tenant_id', $this->tenantId)->findOrFail($export->exam_id);
                $registrations = McqRegistration::where('exam_id', $exam->id)
                    ->whereIn('id', $export->registration_ids)->active()->whereNotNull('hall_ticket_no')
                    ->with(['student.schoolClass', 'teacher', 'school'])->orderBy('hall_ticket_no')->get();
                if ($registrations->isEmpty()) {
                    throw new \RuntimeException('No issued admit cards remain in this selection.');
                }
                $design = McqHallTicketDesign::fromExam($exam);
                $artwork = in_array($design['layout'] ?? '', ['stse_three_up', 'custom_background'], true);
                $html = view('mcq.hall-tickets-bulk', compact('exam', 'registrations', 'design') + ['pdfDownload' => true])->render();
                $bytes = PdfGenerator::render($html, isLandscape: $artwork && ($design['page_orientation'] ?? 'landscape') === 'landscape');
                $path = "mcq-admit-cards/{$this->tenantId}/{$exam->id}/{$export->id}/{$export->filename}";
                if (! Storage::disk($export->storage_disk)->put($path, $bytes, ['visibility' => 'private'])) {
                    throw new \RuntimeException('Could not save the admit card PDF.');
                }
                $export->update(['status' => 'completed', 'file_path' => $path,
                    'total_count' => $registrations->count(), 'completed_at' => now()]);
            } catch (\Throwable $e) {
                $export->update(['status' => 'failed']);
                throw $e;
            }
        });
    }

    public function failed(?\Throwable $exception): void
    {
        $tenant = Tenant::find($this->tenantId);
        if ($tenant) {
            TenancyDatabase::withTenantDatabase($tenant, fn () => McqAdmitCardExport::where('tenant_id', $this->tenantId)
                ->whereKey($this->exportId)->where('status', '!=', 'completed')->update(['status' => 'failed']));
        }
    }
}
