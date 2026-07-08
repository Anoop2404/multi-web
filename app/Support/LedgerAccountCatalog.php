<?php

namespace App\Support;

use App\Models\FestEvent;

class LedgerAccountCatalog
{
    /** @return array{name: string, type: string, category: string} */
    public static function definition(string $code): array
    {
        return match ($code) {
            'CASH-BANK'        => ['name' => 'Cash & Bank', 'type' => 'asset', 'category' => 'asset'],
            'MEMBERSHIP'       => ['name' => 'Membership Fees', 'type' => 'income', 'category' => 'membership'],
            'EVENT-FEE'        => ['name' => 'Event Registration Fees (legacy)', 'type' => 'income', 'category' => 'event'],
            'TRAINING-FEE'     => ['name' => 'Training Program Fees (legacy rollup)', 'type' => 'income', 'category' => 'training'],
            'MCQ-FEE'          => ['name' => 'Talent Search Exam Fees', 'type' => 'income', 'category' => 'mcq'],
            'SPORTS-FEE'       => ['name' => 'Sports Meet Fees (rollup)', 'type' => 'income', 'category' => 'sports'],
            'STATE-REMITTANCE' => ['name' => 'State Remittances', 'type' => 'expense', 'category' => 'expense'],
            'AWARDS-FUND'      => ['name' => 'Awards & Prizes Fund', 'type' => 'expense', 'category' => 'expense'],
            'VENUE-COST'       => ['name' => 'Venue & Infrastructure', 'type' => 'expense', 'category' => 'expense'],
            'CATERING'         => ['name' => 'Catering', 'type' => 'expense', 'category' => 'expense'],
            'PRINTING'         => ['name' => 'Printing & Stationery', 'type' => 'expense', 'category' => 'expense'],
            'TRAVEL-REIMB'     => ['name' => 'Travel Reimbursement', 'type' => 'expense', 'category' => 'expense'],
            'PRIZES'           => ['name' => 'Prizes & Trophies', 'type' => 'expense', 'category' => 'expense'],
            'HONORARIUM'       => ['name' => 'Staff Honorarium', 'type' => 'expense', 'category' => 'expense'],
            'ADMIN-EXP'        => ['name' => 'Administrative Expenses', 'type' => 'expense', 'category' => 'expense'],
            'OPENING-BAL'      => ['name' => 'Opening Balance Equity', 'type' => 'liability', 'category' => 'other'],
            'ACC-PAYABLE'      => ['name' => 'Accounts Payable', 'type' => 'liability', 'category' => 'expense'],
            default            => [
                'name'     => match (true) {
                    str_starts_with($code, 'SPT-') => 'Sports meet fees',
                    str_starts_with($code, 'EVT-') => 'Event fees',
                    str_starts_with($code, 'MCQ-') && $code !== 'MCQ-FEE' => 'Talent Search exam fees',
                    str_starts_with($code, 'TRN-') => 'Training program fees',
                    default                          => $code,
                },
                'type'     => 'income',
                'category' => match (true) {
                    str_starts_with($code, 'SPT-') => 'sports',
                    str_starts_with($code, 'EVT-') => 'event',
                    str_starts_with($code, 'MCQ-') && $code !== 'MCQ-FEE' => 'mcq',
                    str_starts_with($code, 'TRN-') => 'training',
                    default                          => 'other',
                },
            ],
        };
    }

    public static function categoryForCode(string $code): string
    {
        return self::definition($code)['category'];
    }

    public static function eventFeeCode(int|string $eventId): string
    {
        return 'EVT-'.strtoupper(substr(str_replace('-', '', (string) $eventId), 0, 8));
    }

    public static function sportsEventFeeCode(int|string $eventId): string
    {
        return 'SPT-'.strtoupper(substr(str_replace('-', '', (string) $eventId), 0, 8));
    }

    public static function festIncomeCode(FestEvent $event): string
    {
        return $event->event_type === 'sports'
            ? self::sportsEventFeeCode($event->id)
            : self::eventFeeCode($event->id);
    }

    public static function festIncomeCategory(FestEvent $event): string
    {
        return $event->event_type === 'sports' ? 'sports' : 'event';
    }

    public static function festIncomeHeadName(FestEvent $event): string
    {
        $level = config("fest_fees.level_labels.{$event->level_round}", $event->level_round);
        $suffix = $event->event_type === 'sports' ? 'Sports fees' : 'Event fees';

        return "{$event->title} ({$level}) — {$suffix}";
    }

    public static function mcqExamFeeCode(int|string $examId): string
    {
        return 'MCQ-'.strtoupper(substr(str_replace('-', '', (string) $examId), 0, 8));
    }

    public static function mcqExamIncomeHeadName(\App\Models\McqExam $exam): string
    {
        $level = \App\Support\Mcq\McqExamLevelLabels::levelLabel((int) ($exam->exam_level ?? 1));

        return "{$exam->title} ({$level}) — Talent Search fees";
    }

    public static function trainingProgramFeeCode(int|string $programId): string
    {
        return 'TRN-'.str_pad((string) $programId, 6, '0', STR_PAD_LEFT);
    }

    public static function trainingProgramIncomeHeadName(\App\Models\TrainingProgram $program): string
    {
        return "{$program->title} — Training fees";
    }

    /** @deprecated Use festIncomeHeadName() */
    public static function eventFeeHeadName(FestEvent $event): string
    {
        return self::festIncomeHeadName($event);
    }

    /** @return list<string> */
    public static function defaultCodes(): array
    {
        return ['CASH-BANK', 'MEMBERSHIP', 'EVENT-FEE', 'TRAINING-FEE', 'MCQ-FEE', 'SPORTS-FEE', 'STATE-REMITTANCE', 'AWARDS-FUND', 'VENUE-COST', 'CATERING', 'PRINTING', 'TRAVEL-REIMB', 'PRIZES', 'HONORARIUM', 'ADMIN-EXP', 'OPENING-BAL', 'ACC-PAYABLE'];
    }

    /** @return array<string, string> */
    public static function categoryLabels(): array
    {
        return [
            'membership' => 'Membership',
            'event'      => 'Fest events',
            'sports'     => 'Sports meet',
            'training'   => 'Training',
            'mcq'        => 'Talent Search Exams',
            'expense'    => 'Expenses',
            'asset'      => 'Assets',
            'other'      => 'Other',
        ];
    }
}
