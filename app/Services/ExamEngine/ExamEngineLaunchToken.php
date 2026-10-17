<?php

namespace App\Services\ExamEngine;

use App\Models\McqRegistration;

/**
 * One-click entry from the student portal (exam-engine/CONTRACT.md §5.3): an Ed25519-signed,
 * single-use token valid for 2 minutes, so a student already signed in to the portal doesn't
 * re-type their password (and the engine skips a bcrypt check).
 */
class ExamEngineLaunchToken
{
    public const TTL_SECONDS = 120;

    public function isConfigured(): bool
    {
        return filled(config('exam_engine.launch_secret_key'));
    }

    public function issue(McqRegistration $registration): string
    {
        $secret = base64_decode((string) config('exam_engine.launch_secret_key'), true);
        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new ExamEngineException('EXAM_ENGINE_LAUNCH_SECRET_KEY is missing or invalid (run php artisan exam-engine:keys).');
        }

        $registration->loadMissing('exam');
        $payload = json_encode([
            'run'    => ExamEngineRun::for($registration->exam),
            'reg_id' => $registration->id,
            'exp'    => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
            'jti'    => bin2hex(random_bytes(16)),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return self::b64url($payload).'.'.self::b64url(sodium_crypto_sign_detached($payload, $secret));
    }

    /** The exam site URL that logs this student straight in. */
    public function launchUrl(McqRegistration $registration): string
    {
        $registration->loadMissing('exam');

        return ExamEngineBridge::examSiteUrl($registration->exam).'?launch='.rawurlencode($this->issue($registration));
    }

    private static function b64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
