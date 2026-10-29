<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * Short per-registration codes for venue / lab join without portal password login.
 * Distinct from engine exam_passcode (10-char HMAC login on the exam site).
 */
class McqVenueKeycodeService
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const LENGTH = 8;

    public function ensure(McqRegistration $registration): string
    {
        if (! Schema::hasColumn('mcq_registrations', 'venue_keycode')) {
            return '';
        }

        if (filled($registration->venue_keycode)) {
            return strtoupper((string) $registration->venue_keycode);
        }

        return $this->assignNew($registration);
    }

    public function ensureForExam(McqExam $exam): int
    {
        if (! Schema::hasColumn('mcq_registrations', 'venue_keycode')) {
            return 0;
        }

        if (! $exam->allowsVenueKeycodeJoin()) {
            return 0;
        }

        $count = 0;
        McqRegistration::where('exam_id', $exam->id)
            ->active()
            ->whereNull('venue_keycode')
            ->each(function (McqRegistration $registration) use (&$count) {
                $this->assignNew($registration);
                $count++;
            });

        return $count;
    }

    public function findByCode(string $raw): ?McqRegistration
    {
        if (! Schema::hasColumn('mcq_registrations', 'venue_keycode')) {
            return null;
        }

        $code = $this->normalize($raw);
        if ($code === '' || strlen($code) < 6) {
            return null;
        }

        return McqRegistration::query()
            ->where('venue_keycode', $code)
            ->active()
            ->with(['exam', 'student.user'])
            ->first();
    }

    public static function format(?string $code): ?string
    {
        if (! $code) {
            return null;
        }
        $code = strtoupper($code);

        return strlen($code) === 8
            ? substr($code, 0, 4).'-'.substr($code, 4)
            : $code;
    }

    private function assignNew(McqRegistration $registration): string
    {
        for ($i = 0; $i < 12; $i++) {
            $code = $this->randomCode();
            try {
                $registration->forceFill(['venue_keycode' => $code])->save();

                return $code;
            } catch (QueryException) {
                // unique collision — retry
            }
        }

        throw new \RuntimeException('Could not allocate a unique venue keycode.');
    }

    private function randomCode(): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }

    private function normalize(string $raw): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
    }
}
