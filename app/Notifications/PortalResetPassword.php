<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\SahodayaMailer;
use App\Support\Mail\EmailBranding;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class PortalResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subjectFor($notifiable))
            ->view('emails.reset-password', $this->viewDataFor($notifiable));
    }

    public function deliverVia(SahodayaMailer $mailer, User $user): void
    {
        $mailer->sendView(
            $user->email,
            $this->subjectFor($user),
            'emails.reset-password',
            $this->viewDataFor($user),
        );
    }

    /** @return array<string, mixed> */
    public function viewDataFor(User $notifiable): array
    {
        [$school, $sahodaya] = $this->tenantsFor($notifiable);
        $branding = EmailBranding::forTenant($sahodaya);

        return array_merge($branding, [
            'headerTitle' => 'Password Reset',
            'headerSubtitle' => $school?->name,
            'headerEyebrow' => 'School Portal',
            'userName' => $notifiable->name,
            'schoolName' => $school?->name,
            'resetUrl' => $this->resetUrl($notifiable),
            'expireMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
        ]);
    }

    public function subjectFor(User $notifiable): string
    {
        [, $sahodaya] = $this->tenantsFor($notifiable);
        $branding = EmailBranding::forTenant($sahodaya);

        return 'Reset your password — '.($branding['sahodayaName'] ?? config('app.name'));
    }

    /** @return array{0: ?Tenant, 1: ?Tenant} */
    private function tenantsFor(User $notifiable): array
    {
        $school = $notifiable->tenant_id ? Tenant::query()->find($notifiable->tenant_id) : null;
        $sahodaya = $school?->parent_id ? Tenant::query()->find($school->parent_id) : $school;

        return [$school, $sahodaya];
    }
}
