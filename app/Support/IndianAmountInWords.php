<?php

namespace App\Support;

class IndianAmountInWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    public static function rupees(float|int|string $amount): string
    {
        $amount = round((float) $amount, 2);
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        if ($rupees === 0 && $paise === 0) {
            return 'Zero Rupees Only';
        }

        $words = self::convertIndian($rupees).' Rupees';

        if ($paise > 0) {
            $words .= ' and '.self::convertIndian($paise).' Paise';
        }

        return $words.' Only';
    }

    private static function convertIndian(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        if ($number < 100) {
            return trim(self::TENS[intdiv($number, 10)].' '.self::ONES[$number % 10]);
        }

        if ($number < 1000) {
            return trim(self::convertIndian(intdiv($number, 100)).' Hundred '.self::convertIndian($number % 100));
        }

        if ($number < 100000) {
            return trim(self::convertIndian(intdiv($number, 1000)).' Thousand '.self::convertIndian($number % 1000));
        }

        if ($number < 10000000) {
            return trim(self::convertIndian(intdiv($number, 100000)).' Lakh '.self::convertIndian($number % 100000));
        }

        return trim(self::convertIndian(intdiv($number, 10000000)).' Crore '.self::convertIndian($number % 10000000));
    }
}
