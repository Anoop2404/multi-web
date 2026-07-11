<?php

namespace App\Console\Commands;

use App\Models\BoardResult;
use App\Models\Tenant;
use App\Services\BoardResults\BoardResultNotifier;
use App\Support\AcademicYear;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class SendBoardResultUploadReminders extends Command
{
    protected $signature = 'board-results:upload-reminders {--tenant= : Sahodaya tenant id}';

    protected $description = 'Remind schools with draft board results missing PDF for the active academic year';

    public function handle(BoardResultNotifier $notifier): int
    {
        $tenantId = $this->option('tenant');
        $sahodayas = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($tenantId, fn ($q) => $q->where('id', $tenantId))
            ->get();

        $sent = 0;
        foreach ($sahodayas as $sahodaya) {
            TenancyDatabase::withTenantDatabase($sahodaya, function () use ($sahodaya, $notifier, &$sent) {
                $year = AcademicYear::forSahodaya($sahodaya->id);
                $schoolIds = Tenant::query()
                    ->where('parent_id', $sahodaya->id)
                    ->where('type', 'school')
                    ->pluck('id');

                $drafts = BoardResult::query()
                    ->whereIn('tenant_id', $schoolIds)
                    ->where('academic_year', $year)
                    ->where('status', BoardResult::STATUS_DRAFT)
                    ->where(function ($q) {
                        $q->whereNull('result_pdf_path')->orWhere('result_pdf_path', '');
                    })
                    ->get();

                foreach ($drafts as $draft) {
                    $notifier->uploadReminder($draft);
                    $sent++;
                }
            });
        }

        $this->info("Sent {$sent} upload reminder(s).");

        return self::SUCCESS;
    }
}
