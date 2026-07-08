<?php

namespace App\Support\Mcq;

class McqExamLevelLabels
{
    public static function eligibilityModeLabel(?string $mode, ?float $cutoff = null, ?int $topRank = null): string
    {
        return match ($mode ?? 'open') {
            'cutoff_marks' => 'Cutoff ≥ '.number_format((float) $cutoff, 2),
            'top_rank'     => 'Top '.(int) $topRank.' ranks',
            'manual'       => 'Manual selection',
            default        => 'Open (class/gender rules)',
        };
    }

    public static function examTypeLabel(?string $type): string
    {
        return match ($type ?? 'assessment') {
            'practice'     => 'Practice',
            'competitive'  => 'Competitive',
            default        => 'Assessment',
        };
    }

    public static function levelLabel(int $level): string
    {
        return 'Level '.$level;
    }

    public static function feeLabel(?string $feeType, mixed $feeAmount): string
    {
        return self::rupeeLabel($feeAmount, ($feeType ?? 'none') === 'none' ? 0 : null);
    }

    public static function rupeeLabel(mixed $amount, ?float $emptyBelow = 0): string
    {
        if ($emptyBelow !== null && (float) ($amount ?? 0) <= $emptyBelow) {
            return 'Not set';
        }

        $value = (float) $amount;

        return '₹'.($value == floor($value) ? number_format($value, 0) : number_format($value, 2));
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'published' => 'Open for registration',
            'ongoing'   => 'Exam ongoing',
            'completed' => 'Completed',
            'draft'     => 'Draft',
            'cancelled' => 'Cancelled',
            default     => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }
}
