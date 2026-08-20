<?php

namespace App\Services\Mail;

use App\Models\FailedEmailLog;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Mail\EmailBranding;
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
        return (bool) $this->profile()?->mailIsConfigured();
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

        try {
            $this->attemptSendRaw($to, $subject, $body);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send raw mail, queueing failed log', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);

            $this->logFailedMail($to, $subject, 'raw', null, ['body' => $body], $e->getMessage());
        }
    }

    /** @param  list<array{content: string, name: string, mime?: string}>  $attachments */
    public function sendViewWithAttachments(
        string $to,
        string $subject,
        string $view,
        array $data = [],
        array $attachments = [],
    ): void {
        if ($to === '') {
            return;
        }

        try {
            $this->attemptSendView($to, $subject, $view, $data, $attachments);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send view mail, queueing failed log', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);

            $this->logFailedMail($to, $subject, 'view', $view, ['data' => $data], $e->getMessage());

            throw $e;
        }
    }

    /** @param  list<string>  $recipients  @param  array<string, mixed>  $data  @param  list<array{content: string, name: string, mime?: string}>  $attachments */
    public function sendViewToManyWithAttachments(
        array $recipients,
        string $subject,
        string $view,
        array $data = [],
        array $attachments = [],
    ): void {
        foreach (array_unique(array_filter($recipients)) as $email) {
            $this->sendViewWithAttachments($email, $subject, $view, $data, $attachments);
        }
    }

    /** @param  array<string, mixed>  $data */
    public function sendView(string $to, string $subject, string $view, array $data = []): void
    {
        $this->sendViewWithAttachments($to, $subject, $view, $data);
    }

    /** @param  list<string>  $recipients  @param  array<string, mixed>  $data */
    public function sendViewToMany(array $recipients, string $subject, string $view, array $data = []): void
    {
        foreach (array_unique(array_filter($recipients)) as $email) {
            $this->sendView($email, $subject, $view, $data);
        }
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
        try {
            $this->attemptSendVerification($user);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send verification email, queueing failed log', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);

            $this->logFailedMail(
                $user->email,
                'Verify Your Email Address',
                'verification',
                null,
                ['user_id' => $user->id],
                $e->getMessage(),
                $user->name,
            );
        }
    }

    public function sendPasswordReset(User $user, string $token): void
    {
        try {
            $this->attemptSendPasswordReset($user, $token);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send password reset email, queueing failed log', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);

            $this->logFailedMail(
                $user->email,
                'Reset Password Notification',
                'password_reset',
                null,
                ['user_id' => $user->id, 'token' => $token],
                $e->getMessage(),
                $user->name,
            );
        }
    }

    public function retryFailedMail(FailedEmailLog $log): bool
    {
        $log->increment('attempts');
        $log->update(['last_attempted_at' => now()]);

        try {
            $to = $log->recipient_email;
            $subject = $log->subject;
            $payload = $log->payload ?? [];

            switch ($log->mail_type) {
                case 'raw':
                    $this->attemptSendRaw($to, $subject, $payload['body'] ?? '');
                    break;
                case 'verification':
                    if (isset($payload['user_id']) && $user = User::find($payload['user_id'])) {
                        $this->attemptSendVerification($user);
                    } else {
                        throw new \RuntimeException('User for verification email not found.');
                    }
                    break;
                case 'password_reset':
                    if (isset($payload['user_id'], $payload['token']) && $user = User::find($payload['user_id'])) {
                        $this->attemptSendPasswordReset($user, $payload['token']);
                    } else {
                        throw new \RuntimeException('User or token for password reset email not found.');
                    }
                    break;
                case 'view':
                default:
                    $view = $log->mail_view ?? 'emails.generic';
                    $this->attemptSendView($to, $subject, $view, $payload['data'] ?? [], $payload['attachments'] ?? []);
                    break;
            }

            $log->update([
                'status'        => 'retry_success',
                'sent_at'       => now(),
                'error_message' => null,
            ]);

            return true;
        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'retry_failed',
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function logFailedMail(
        string $to,
        string $subject,
        string $mailType,
        ?string $view = null,
        array $payload = [],
        string $errorMessage = '',
        ?string $recipientName = null,
    ): FailedEmailLog {
        return FailedEmailLog::create([
            'sahodaya_id'       => $this->sahodayaId,
            'recipient_email'   => $to,
            'recipient_name'    => $recipientName,
            'subject'           => $subject,
            'mail_type'         => $mailType,
            'mail_view'         => $view,
            'payload'           => $payload,
            'error_message'     => $errorMessage,
            'status'            => 'pending',
            'attempts'          => 1,
            'last_attempted_at'  => now(),
        ]);
    }

    private function attemptSendRaw(string $to, string $subject, string $body): void
    {
        if ($this->isConfigured() && $this->usesZeptoMailApi()) {
            try {
                [$fromAddress, $fromName] = $this->fromAddress();
                $this->zeptoClient()->send(
                    $fromAddress ?? '',
                    $fromName,
                    [['address' => $to]],
                    $subject,
                    nl2br(e($body)),
                    $body,
                );

                return;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('ZeptoMail API send failed, falling back to default SMTP mailer', [
                    'to'    => $to,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $mailer = $this->resolveMailerName();
        [$fromAddress, $fromName] = $this->fromAddress();

        Mail::mailer($mailer)->raw($body, function ($message) use ($to, $subject, $fromAddress, $fromName) {
            $message->to($to)->subject($subject);

            $actualFrom = $fromAddress ?: config('mail.from.address');
            $actualName = $fromName ?: config('mail.from.name');

            if ($actualFrom) {
                $message->from($actualFrom, $actualName);
            }
        });
    }

    private function attemptSendView(
        string $to,
        string $subject,
        string $view,
        array $data = [],
        array $attachments = [],
    ): void {
        if ($this->isConfigured() && $this->usesZeptoMailApi()) {
            try {
                $this->sendHtmlViaApi($to, $subject, $view, $data, $attachments);

                return;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('ZeptoMail API send failed, falling back to default SMTP mailer', [
                    'to'    => $to,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->sendViaSmtpMailer($to, $subject, $view, $data, $attachments);
    }

    private function attemptSendVerification(User $user): void
    {
        if ($this->isConfigured() && $this->usesZeptoMailApi()) {
            try {
                (new \App\Notifications\PortalVerifyEmail)->deliverVia($this, $user);

                return;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('ZeptoMail API verification email failed, falling back to default SMTP mailer', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $this->withSahodayaMailer(function () use ($user) {
            $user->sendEmailVerificationNotification();
        });
    }

    private function attemptSendPasswordReset(User $user, string $token): void
    {
        if ($this->isConfigured() && $this->usesZeptoMailApi()) {
            try {
                (new \App\Notifications\PortalResetPassword($token))->deliverVia($this, $user);

                return;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('ZeptoMail API password reset failed, falling back to default SMTP mailer', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $this->withSahodayaMailer(function () use ($user, $token) {
            $user->notify(new \App\Notifications\PortalResetPassword($token));
        });
    }

    /**
     * Run a mail callback using this Sahodaya's mail settings.
     */
    public function withSahodayaMailer(callable $callback): void
    {
        if (! $this->isConfigured() || $this->usesZeptoMailApi()) {
            $callback();

            return;
        }

        $mailer = $this->resolveMailerName();
        [$fromAddress, $fromName] = $this->fromAddress();
        $previousDefault = config('mail.default');
        $previousFrom = config('mail.from');

        Config::set('mail.default', $mailer);

        if ($fromAddress) {
            Config::set('mail.from', [
                'address' => $fromAddress,
                'name'    => $fromName ?: ($previousFrom['name'] ?? config('app.name')),
            ]);
        }

        try {
            $callback();
        } finally {
            Config::set('mail.default', $previousDefault);
            Config::set('mail.from', $previousFrom);
        }
    }

    /** @return array<string, mixed> */
    public function brandingData(): array
    {
        return EmailBranding::forTenant($this->sahodaya(), $this->profile());
    }

    /** @param  array<string, mixed>  $data  @param  list<array{content: string, name: string, mime?: string}>  $attachments */
    private function sendHtmlViaApi(string $to, string $subject, string $view, array $data = [], array $attachments = []): void
    {
        [$fromAddress, $fromName] = $this->fromAddress();
        $html = view($view, $this->viewData($data))->render();

        $this->zeptoClient()->send(
            $fromAddress ?? '',
            $fromName,
            [['address' => $to]],
            $subject,
            $html,
            attachments: $attachments,
        );
    }

    /** @param  array<string, mixed>  $data  @param  list<array{content: string, name: string, mime?: string}>  $attachments */
    private function sendViaSmtpMailer(
        string $to,
        string $subject,
        string $view,
        array $data = [],
        array $attachments = [],
    ): void {
        $mailer = $this->resolveMailerName();
        [$fromAddress, $fromName] = $this->fromAddress();

        Mail::mailer($mailer)->send($view, $this->viewData($data), function ($message) use ($to, $subject, $fromAddress, $fromName, $attachments) {
            $message->to($to)->subject($subject);

            $actualFrom = $fromAddress ?: config('mail.from.address');
            $actualName = $fromName ?: config('mail.from.name');

            if ($actualFrom) {
                $message->from($actualFrom, $actualName);
            }

            foreach ($attachments as $attachment) {
                $message->attachData(
                    $attachment['content'],
                    $attachment['name'],
                    ['mime' => $attachment['mime'] ?? 'application/octet-stream'],
                );
            }
        });
    }

    /** @param  array<string, mixed>  $data  @return array<string, mixed> */
    private function viewData(array $data): array
    {
        return array_merge($this->brandingData(), $data);
    }

    private function usesZeptoMailApi(): bool
    {
        return (bool) $this->profile()?->usesZeptoMailApi();
    }

    private function zeptoClient(): ZeptoMailApiClient
    {
        $profile = $this->profile();

        return new ZeptoMailApiClient(
            (string) $profile?->mail_password,
            $profile?->zeptomail_region ?: 'in',
        );
    }

    private function resolveMailerName(): string
    {
        if (! $this->isConfigured()) {
            return (string) config('mail.default', 'smtp');
        }

        $mailerName = 'sahodaya_'.$this->sahodayaId;
        $profile = $this->profile();
        $defaultHost = strtolower((string) $profile->mail_username) === 'emailapikey'
            ? 'smtp.zeptomail.in'
            : config('mail.mailers.smtp.host', 'smtp.zoho.in');

        Config::set('mail.mailers.'.$mailerName, [
            'transport'    => 'smtp',
            'host'         => $profile->mail_host ?: $defaultHost,
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
