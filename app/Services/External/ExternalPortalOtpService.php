<?php

namespace App\Services\External;

use App\Models\ExternalSahodaya;
use App\Models\ExternalSchool;
use App\Notifications\ExternalPortalOtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * P-02 (docs/STATE_KALOTSAV_MASTER_IMPLEMENTATION_PLAN.md §23) — email+OTP checkpoint in
 * front of the external Sahodaya/school portal's access-code auth. The access code still
 * identifies *which* record; this proves the person holding the link is also the registered
 * coordinator, closing the "URL is a bearer credential" gap the plan flags as rejected-risk.
 *
 * Works generically across ExternalSahodaya and ExternalSchool — both share the same
 * otp_code_hash/otp_expires_at/otp_last_sent_at/otp_attempts columns (migration
 * 2026_08_11_000001_external_portal_otp.php).
 */
class ExternalPortalOtpService
{
    private const EXPIRES_MINUTES = 10;

    private const RESEND_COOLDOWN_SECONDS = 45;

    private const MAX_ATTEMPTS = 5;

    public function issue(ExternalSahodaya|ExternalSchool $account): void
    {
        abort_unless($account->requiresOtp(), 422, 'No email on file for this record — contact the State Kalolsavam office.');

        if ($account->otp_last_sent_at && $account->otp_last_sent_at->addSeconds(self::RESEND_COOLDOWN_SECONDS)->isFuture()) {
            $wait = now()->diffInSeconds($account->otp_last_sent_at->addSeconds(self::RESEND_COOLDOWN_SECONDS));
            abort(429, "Please wait {$wait}s before requesting another code.");
        }

        $code = (string) random_int(100000, 999999);

        $account->forceFill([
            'otp_code_hash'    => Hash::make($code),
            'otp_expires_at'   => now()->addMinutes(self::EXPIRES_MINUTES),
            'otp_last_sent_at' => now(),
            'otp_attempts'     => 0,
        ])->save();

        Notification::route('mail', $account->contact_email)
            ->notify(new ExternalPortalOtpCode($code, $account->name, self::EXPIRES_MINUTES));
    }

    public function verify(ExternalSahodaya|ExternalSchool $account, string $code): bool
    {
        if (! $account->otp_code_hash || ! $account->otp_expires_at) {
            return false;
        }

        abort_if($account->otp_attempts >= self::MAX_ATTEMPTS, 429, 'Too many incorrect attempts. Request a new code.');

        if ($account->otp_expires_at->isPast()) {
            return false;
        }

        if (! Hash::check($code, $account->otp_code_hash)) {
            $account->increment('otp_attempts');

            return false;
        }

        // One-time: clear it so the same code can't be replayed, and reset the resend cooldown
        // so a follow-up sign-in (new browser, session expired) can request fresh immediately.
        $account->forceFill([
            'otp_code_hash'    => null,
            'otp_expires_at'   => null,
            'otp_last_sent_at' => null,
            'otp_attempts'     => 0,
        ])->save();

        return true;
    }

    /** @param  ExternalSahodaya|ExternalSchool  $account */
    public function markSessionVerified($account): void
    {
        session()->put($this->sessionKey($account), true);
    }

    /** @param  ExternalSahodaya|ExternalSchool  $account */
    public function isSessionVerified($account): bool
    {
        return (bool) session($this->sessionKey($account));
    }

    /** @param  ExternalSahodaya|ExternalSchool  $account */
    private function sessionKey($account): string
    {
        return 'external_portal_verified.'.$account->getMorphClass().'.'.$account->getKey();
    }
}
