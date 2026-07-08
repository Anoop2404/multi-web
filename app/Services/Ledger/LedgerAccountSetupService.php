<?php

namespace App\Services\Ledger;

use App\Models\AccountHead;
use App\Models\FestEvent;
use App\Models\McqExam;
use App\Models\TrainingProgram;
use App\Support\LedgerAccountCatalog;

class LedgerAccountSetupService
{
    public function ensureFestEventHead(FestEvent $event): AccountHead
    {
        return app(LedgerPostingService::class)->ensureHead(
            $event->tenant_id,
            LedgerAccountCatalog::festIncomeCode($event),
            LedgerAccountCatalog::festIncomeHeadName($event),
            LedgerAccountCatalog::festIncomeCategory($event),
            $event->id,
        );
    }

    public function ensureMcqExamHead(McqExam $exam): AccountHead
    {
        return app(LedgerPostingService::class)->ensureHead(
            $exam->tenant_id,
            LedgerAccountCatalog::mcqExamFeeCode($exam->id),
            LedgerAccountCatalog::mcqExamIncomeHeadName($exam),
            'mcq',
            null,
            $exam->id,
        );
    }

    public function ensureTrainingProgramHead(TrainingProgram $program): AccountHead
    {
        return app(LedgerPostingService::class)->ensureHead(
            $program->tenant_id,
            LedgerAccountCatalog::trainingProgramFeeCode($program->id),
            LedgerAccountCatalog::trainingProgramIncomeHeadName($program),
            'training',
            null,
            null,
            $program->id,
        );
    }

    /** @return array{code: string, name: string, head: AccountHead|null, ledger_url: string} */
    public function festLedgerMeta(FestEvent $event, string $tenantId): array
    {
        $code = LedgerAccountCatalog::festIncomeCode($event);
        $head = AccountHead::where('tenant_id', $event->tenant_id)->where('code', $code)->first();

        if (! $head) {
            $head = $this->ensureFestEventHead($event);
        }

        return [
            'code'       => $code,
            'name'       => $head->name,
            'head_id'    => $head->id,
            'head'       => $head,
            'ledger_url' => "/sahodaya-admin/{$tenantId}/events/{$event->id}/fees/ledger",
        ];
    }

    /** @return array{code: string, name: string, head: AccountHead|null, ledger_url: string} */
    public function mcqLedgerMeta(McqExam $exam, string $tenantId): array
    {
        $code = LedgerAccountCatalog::mcqExamFeeCode($exam->id);
        $head = AccountHead::where('tenant_id', $exam->tenant_id)->where('code', $code)->first();

        if (! $head) {
            $head = $this->ensureMcqExamHead($exam);
        }

        return [
            'code'       => $code,
            'name'       => $head->name,
            'head_id'    => $head->id,
            'head'       => $head,
            'ledger_url' => "/sahodaya-admin/{$tenantId}/mcq-exams/{$exam->id}/ledger",
        ];
    }

    /** @return array{code: string, name: string, head: AccountHead|null, ledger_url: string} */
    public function trainingLedgerMeta(TrainingProgram $program, string $tenantId): array
    {
        $code = LedgerAccountCatalog::trainingProgramFeeCode($program->id);
        $head = AccountHead::where('tenant_id', $program->tenant_id)->where('code', $code)->first();

        if (! $head) {
            $head = $this->ensureTrainingProgramHead($program);
        }

        return [
            'code'       => $code,
            'name'       => $head->name,
            'head_id'    => $head->id,
            'head'       => $head,
            'ledger_url' => "/sahodaya-admin/{$tenantId}/training/{$program->id}/ledger",
        ];
    }

    public function updateHeadName(AccountHead $head, string $name): AccountHead
    {
        $head->update(['name' => $name]);

        return $head->fresh();
    }
}
