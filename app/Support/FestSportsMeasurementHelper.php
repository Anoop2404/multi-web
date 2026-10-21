<?php

namespace App\Support;

class FestSportsMeasurementHelper
{
    /**
     * Parse a time string into comparable seconds (float).
     *
     * Supports:
     * - "11.25", "11.2", "58" (seconds)
     * - "1:02.50", "1:02.5", "02:15.30", "14:25.00" (minutes:seconds[.milliseconds])
     * - "1:05:20", "01:15:30.50" (hours:minutes:seconds[.milliseconds])
     * - "1m 23s", "1m 23.4s", "12.5s" (natural shorthand)
     *
     * Returns null if the format is invalid or cannot be parsed.
     */
    public static function parseTimeInSeconds(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $val = trim($value);
        if ($val === '') {
            return null;
        }

        // Shorthand with 'm' and optional 's': e.g. "1m 23.4s" or "2m 10s"
        if (preg_match('/^(\d+)\s*m(?:in)?(?:\s*([0-5]?\d(?:[.,]\d{1,3})?)\s*s(?:ec)?)?$/i', $val, $m)) {
            $mins = (float) $m[1];
            $secs = isset($m[2]) ? (float) str_replace(',', '.', $m[2]) : 0.0;
            if ($secs >= 60.0) {
                return null;
            }

            return $mins * 60.0 + $secs;
        }

        // Strip trailing 's' or 'sec' for pure second inputs (e.g. "12.45s")
        $val = preg_replace('/\s*s(ec)?$/i', '', $val);

        // Hours:Minutes:Seconds format e.g. "1:05:20.50" or "01:12:45"
        if (preg_match('/^(\d{1,2}):([0-5]?\d):([0-5]?\d(?:[.,]\d{1,3})?)$/', $val, $m)) {
            $hours = (float) $m[1];
            $mins = (float) $m[2];
            $secs = (float) str_replace(',', '.', $m[3]);

            return $hours * 3600.0 + $mins * 60.0 + $secs;
        }

        // Minutes:Seconds format e.g. "1:02.50", "14:35.0"
        if (preg_match('/^(\d{1,3}):([0-5]?\d(?:[.,]\d{1,3})?)$/', $val, $m)) {
            $mins = (float) $m[1];
            $secs = (float) str_replace(',', '.', $m[2]);

            return $mins * 60.0 + $secs;
        }

        // Decimal or integer seconds e.g. "11.25" or "54"
        if (preg_match('/^\d+(?:[.,]\d{1,3})?$/', $val)) {
            return (float) str_replace(',', '.', $val);
        }

        return null;
    }

    /**
     * Parse a distance string into meters (float).
     *
     * Supports:
     * - "5.45", "12.30", "1.65" (meters)
     * - "5.45 m", "5.45m", "12.30 meters"
     * - "165 cm", "165cm" (converted to 1.65 m)
     */
    public static function parseDistanceInMeters(?string $value, ?string $unit = 'm'): ?float
    {
        if ($value === null) {
            return null;
        }

        $val = trim($value);
        if ($val === '') {
            return null;
        }

        $defaultUnit = strtolower(trim($unit ?? 'm'));

        if (preg_match('/^(\d+(?:[.,]\d{1,3})?)\s*(cm|m|meter|meters)?$/i', $val, $m)) {
            $num = (float) str_replace(',', '.', $m[1]);
            $detectedUnit = isset($m[2]) ? strtolower($m[2]) : $defaultUnit;

            if ($detectedUnit === 'cm') {
                return $num / 100.0;
            }

            return $num;
        }

        return null;
    }

    /**
     * Parse measurement based on result method (time, distance, or general numeric).
     */
    public static function parseMeasurement(?string $value, string $resultMethod, ?string $unit = null): ?float
    {
        $method = strtolower(trim($resultMethod));

        if ($method === 'time') {
            return self::parseTimeInSeconds($value);
        }

        if ($method === 'distance') {
            return self::parseDistanceInMeters($value, $unit);
        }

        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/[^0-9.]/', '', trim($value));
        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }

    /**
     * Format seconds into a clean display string (e.g. "12.50 s" or "1:02.50").
     */
    public static function formatTime(?float $seconds): string
    {
        if ($seconds === null || $seconds < 0) {
            return '—';
        }

        if ($seconds >= 3600.0) {
            $hours = (int) floor($seconds / 3600.0);
            $rem = $seconds - ($hours * 3600.0);
            $mins = (int) floor($rem / 60.0);
            $secs = $rem - ($mins * 60.0);

            return sprintf('%d:%02d:%05.2f', $hours, $mins, $secs);
        }

        if ($seconds >= 60.0) {
            $mins = (int) floor($seconds / 60.0);
            $secs = $seconds - ($mins * 60.0);

            return sprintf('%d:%05.2f', $mins, $secs);
        }

        return sprintf('%.2f s', $seconds);
    }

    /**
     * Format distance into clean meters string (e.g. "5.45 m").
     */
    public static function formatDistance(?float $meters): string
    {
        if ($meters === null || $meters < 0) {
            return '—';
        }

        return sprintf('%.2f m', $meters);
    }
}
