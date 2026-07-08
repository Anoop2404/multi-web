<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\RateLimiter;

class LoginLockoutService
{
    public function key(string $identifier): string
    {
        return 'login:'.strtolower(trim($identifier));
    }

    public function isLocked(string $identifier): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->key($identifier),
            $this->maxAttempts(),
        );
    }

    public function secondsRemaining(string $identifier): int
    {
        return RateLimiter::availableIn($this->key($identifier));
    }

    public function recordFailedAttempt(string $identifier): void
    {
        RateLimiter::hit($this->key($identifier), $this->decaySeconds());
    }

    public function clear(string $identifier): void
    {
        RateLimiter::clear($this->key($identifier));
    }

    public function lockoutMessage(string $identifier): string
    {
        $minutes = max(1, (int) ceil($this->secondsRemaining($identifier) / 60));

        return "Too many failed login attempts. Please try again in {$minutes} minute(s).";
    }

    private function maxAttempts(): int
    {
        return (int) config('erp.login_max_attempts', 5);
    }

    private function decaySeconds(): int
    {
        return (int) config('erp.login_lockout_minutes', 15) * 60;
    }
}
