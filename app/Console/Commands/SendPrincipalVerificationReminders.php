<?php

namespace App\Console\Commands;

use App\Models\BoardResultCertificationPackage;
use App\Models\Tenant;
use App\Services\BoardResults\BoardResultCertificationNotifier;
use App\Support\ReminderDedupGuard;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

/**
 * Reminds school leadership (Principal/Vice Principal) about certification packages
 * still awaiting their review/signature — plan §11.
 */
class SendPrincipalVerificationReminders extends Command
{
    protected $signature = 'board-results:principal-verification-reminders {--tenant= : Sahodaya tenant id}';

    protected $description = 'Remind Principal/Vice Principal users about certification packages awaiting review or signature';

    private const PENDING_STATUSES = [
        BoardResultCertificationPackage::STATUS_AWAITING_LEADERSHIP_REVIEW,
        BoardResultCertificationPackage::STATUS_AWAITING_REPORT_SIGNATURES,
        BoardResultCertificationPackage::STATUS_INDIVIDUAL_REPORTS_SIGNED,
        BoardResultCertificationPackage::STATUS_AWAITING_CONSOLIDATED_SIGNATURE,
    ];

    public function handle(BoardResultCertificationNotifier $notifier): int
    {
        $tenantId = $this->option('tenant');
        $sahodayas = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($tenantId, fn ($q) => $q->where('id', $tenantId))
            ->get();

        $sent = 0;
        foreach ($sahodayas as $sahodaya) {
            TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($sahodaya, $notifier, &$sent) {
                $schoolIds = Tenant::query()
                    ->where('parent_id', $sahodaya->id)
                    ->where('type', 'school')
                    ->pluck('id');

                $pending = BoardResultCertificationPackage::query()
                    ->whereIn('tenant_id', $schoolIds)
                    ->whereIn('status', self::PENDING_STATUSES)
                    ->get();

                foreach ($pending as $package) {
                    if (! ReminderDedupGuard::claim('board-results:principal-verification-reminders', $sahodaya->id, $package->id)) {
                        continue;
                    }

                    $notifier->reminder($package);
                    $sent++;
                }
            });
        }

        $this->info("Sent {$sent} Principal Verification reminder(s).");

        return self::SUCCESS;
    }
}
