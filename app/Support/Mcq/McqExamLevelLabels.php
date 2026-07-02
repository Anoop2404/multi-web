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
        if (($feeType ?? 'none') === 'none' || (float) ($feeAmount ?? 0) <= 0) {
            return 'Not set';
        }

        $amount = (float) $feeAmount;

        return '₹'.($amount == floor($amount) ? number_format($amount, 0) : number_format($amount, 2));
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
