<?php

namespace App\Services\Mail;

use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenancyDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class SahodayaMailer
{
    private ?SahodayaProfile $profile = null;

    private ?Tenant $sahodaya = null;

    public function __construct(private readonly string $sahodayaId) {}

    public static function for(string $sahodayaId): self
    {
        return new self($sahodayaId);
    }

    public function isConfigured(): bool
    {
        $profile = $this->profile();

        return filled($profile?->mail_username) && filled($profile?->mail_password);
    }

    public function contactEmail(): ?string
    {
        return $this->profile()?->contact_email;
    }

    public function sendRaw(string $to, string $subject, string $body): void
    {
        if ($to === '') {
            return;
        }

        $mailer = $this->resolveMailerName();
        [$fromAddress, $fromName] = $this->fromAddress();

        Mail::mailer($mailer)->raw($body, function ($message) use ($to, $subject, $fromAddress, $fromName) {
            $message->to($to)->subject($subject);

            if ($fromAddress) {
                $message->from($fromAddress, $fromName);
            }
        });
    }

    /** @param  list<string>  $recipients */
    public function sendRawToMany(array $recipients, string $subject, string $body): void
    {
        foreach (array_unique(array_filter($recipients)) as $email) {
            $this->sendRaw($email, $subject, $body);
        }
    }

    public function sendVerification(User $user): void
    {
        if (! $this->isConfigured()) {
            $user->sendEmailVerificationNotification();

            return;
        }

        $mailer = $this->resolveMailerName();
        $previousDefault = config('mail.default');

        Config::set('mail.default', $mailer);

        try {
            $user->sendEmailVerificationNotification();
        } finally {
            Config::set('mail.default', $previousDefault);
        }
    }

    private function resolveMailerName(): string
    {
        if (! $this->isConfigured()) {
            return (string) config('mail.default', 'smtp');
        }

        $mailerName = 'sahodaya_'.$this->sahodayaId;
        $profile = $this->profile();

        Config::set('mail.mailers.'.$mailerName, [
            'transport'    => 'smtp',
            'host'         => $profile->mail_host ?: config('mail.mailers.smtp.host', 'smtp.zoho.in'),
            'port'         => (int) ($profile->mail_port ?: config('mail.mailers.smtp.port', 587)),
            'encryption'   => $profile->mail_encryption ?: 'tls',
            'username'     => $profile->mail_username,
            'password'     => $profile->mail_password,
            'timeout'      => null,
            'local_domain' => config('mail.mailers.smtp.local_domain'),
        ]);

        return $mailerName;
    }

    /** @return array{0: ?string, 1: ?string} */
    private function fromAddress(): array
    {
        $profile = $this->profile();
        $sahodaya = $this->sahodaya();

        $address = $profile?->mail_from_address
            ?: (filter_var($profile?->mail_username, FILTER_VALIDATE_EMAIL) ? $profile->mail_username : null)
            ?: $profile?->contact_email;

        $name = $profile?->mail_from_name ?: $sahodaya?->name;

        return [$address, $name];
    }

    private function profile(): ?SahodayaProfile
    {
        if ($this->profile !== null) {
            return $this->profile;
        }

        return $this->profile = $this->withinSahodayaTenant(function () {
            return SahodayaProfile::query()
                ->where('tenant_id', $this->sahodayaId)
                ->first();
        });
    }

    private function sahodaya(): ?Tenant
    {
        if ($this->sahodaya !== null) {
            return $this->sahodaya;
        }

        return $this->sahodaya = Tenant::query()->find($this->sahodayaId);
    }

    private function withinSahodayaTenant(callable $callback): mixed
    {
        $sahodaya = $this->sahodaya();
        if (! $sahodaya) {
            return null;
        }

        $wasInitialized = tenancy()->initialized;
        $previousTenant = tenant();

        try {
            if (! $wasInitialized || tenant()?->id !== $sahodaya->id) {
                TenancyDatabase::initializeForTenant($sahodaya);
            }

            return $callback();
        } finally {
            if (! $wasInitialized) {
                tenancy()->end();
            } elseif ($previousTenant && tenant()?->id !== $previousTenant->id) {
                TenancyDatabase::initializeForTenant($previousTenant);
            }
        }
    }
}
