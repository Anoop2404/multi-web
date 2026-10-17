<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * Per-registration exam passcodes for the separate online exam login page
 * (docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md §4.2). A passcode is printed on the hall ticket
 * and in the school's login list, so it's stored encrypted (to reprint) plus a keyed hash
 * (`exam_passcode_lookup`) that is unique per exam — hall ticket numbers repeat across
 * classes, so the passcode itself identifies the candidate at login.
 */
class McqExamPasscodeService
{
    /** No 0/O, 1/I/L, so codes survive being read aloud or typed from a printout. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const LENGTH = 10;

    /** The registration's passcode, created on first use. */
    public function ensure(McqRegistration $registration): string
    {
        if (! Schema::hasColumn('mcq_registrations', 'exam_passcode')) {
            return '';
        }

        if (filled($registration->exam_passcode)) {
            return $registration->exam_passcode;
        }

        return $this->assignNew($registration);
    }

    /** Replaces the passcode (lost hall ticket, suspected sharing). The old one stops working. */
    public function reissue(McqRegistration $registration): string
    {
        if (! Schema::hasColumn('mcq_registrations', 'exam_passcode')) {
            return '';
        }

        $code = $this->assignNew($registration);

        app(PlatformAuditLogger::class)->mcqRegistration(
            $registration->fresh(['exam']),
            'mcq.registration.passcode_reissued',
            'Reissued the online exam passcode',
        );

        return $code;
    }

    /** Creates passcodes for every hall-ticketed registration of the exam that lacks one. */
    public function ensureForExam(McqExam $exam): int
    {
        if (! Schema::hasColumn('mcq_registrations', 'exam_passcode')) {
            return 0;
        }

        $count = 0;
        McqRegistration::where('exam_id', $exam->id)
            ->whereNotNull('hall_ticket_no')
            ->whereNull('exam_passcode_lookup')
            ->active()
            ->each(function (McqRegistration $registration) use (&$count) {
                $this->assignNew($registration);
                $count++;
            });

        return $count;
    }

    /** The passcode as printed: two groups of five, e.g. "K7M2P-Q9RTX". */
    public static function format(?string $code): ?string
    {
        return $code ? substr($code, 0, 5).'-'.substr($code, 5) : null;
    }

    /** Finds the registration a typed passcode belongs to (spaces, dashes and case ignored). */
    public function findRegistration(McqExam $exam, string $typed): ?McqRegistration
    {
        if (! Schema::hasColumn('mcq_registrations', 'exam_passcode_lookup')) {
            return null;
        }

        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $typed) ?? '');
        if (strlen($code) !== self::LENGTH) {
            return null;
        }

        return McqRegistration::where('exam_id', $exam->id)
            ->where('exam_passcode_lookup', $this->lookupHash($exam->id, $code))
            ->first();
    }

    private function assignNew(McqRegistration $registration): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = $this->randomCode();
            try {
                $registration->forceFill([
                    'exam_passcode'        => $code,
                    'exam_passcode_lookup' => $this->lookupHash($registration->exam_id, $code),
                ])->save();

                return $code;
            } catch (QueryException $e) {
                // Unique (exam, lookup) collision — astronomically unlikely; draw again.
                if ($attempt === 4) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Could not assign an exam passcode.');
    }

    private function randomCode(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    private function lookupHash(int $examId, string $code): string
    {
        return hash_hmac('sha256', "{$examId}|{$code}", (string) config('app.key'));
    }
}
