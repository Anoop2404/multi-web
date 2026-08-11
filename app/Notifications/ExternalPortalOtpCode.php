<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * P-02 — one-time code for the external Sahodaya/school portal (see migration
 * 2026_08_11_000001_external_portal_otp.php for why this exists). Sent to an anonymous
 * notifiable (Notification::route('mail', $email)->notify(...)) since ExternalSahodaya/
 * ExternalSchool aren't Users — there's no account to attach this to.
 */
class ExternalPortalOtpCode extends Notification
{
    use Queueable;

    public function __construct(
        private string $code,
        private string $recipientLabel,
        private int $expiresInMinutes,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your State Kalolsavam portal code: '.$this->code)
            ->greeting("Hello {$this->recipientLabel},")
            ->line('Use this code to sign in to your State Kalolsavam external portal:')
            ->line(new \Illuminate\Support\HtmlString(
                '<div style="font-size:28px;font-weight:700;letter-spacing:.3em;text-align:center;margin:16px 0;">'.$this->code.'</div>'
            ))
            ->line("This code expires in {$this->expiresInMinutes} minutes.")
            ->line('If you did not request this, you can ignore this email — nobody can access your portal without both your access link and this code.');
    }
}
