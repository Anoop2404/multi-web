<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Mail\EmailBranding;
use App\Support\TenantDomainSync;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class PortalVerifyEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $school = $notifiable->tenant_id
            ? Tenant::query()->find($notifiable->tenant_id)
            : null;

        $sahodaya = $school?->parent_id
            ? Tenant::query()->find($school->parent_id)
            : $school;

        $branding = EmailBranding::forTenant($sahodaya);

        return (new MailMessage)
            ->subject('Verify your Gmail — '.($branding['sahodayaName'] ?? config('app.name')))
            ->view('emails.verify-email', array_merge($branding, [
                'headerTitle'       => 'Gmail Verification',
                'headerSubtitle'    => $school?->name,
                'headerEyebrow'     => 'School Portal',
                'userName'          => $notifiable->name,
                'schoolName'        => $school?->name,
                'verificationUrl'   => $this->verificationUrl($notifiable),
                'verificationMins'  => (int) Config::get('auth.verification.expire', 60),
            ]));
    }

    protected function verificationUrl($notifiable): string
    {
        $portal = self::portalUrlFor($notifiable);
        $root = config('app.url');

        if ($portal) {
            URL::forceRootUrl($portal);
        }

        try {
            return URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id'   => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ],
            );
        } finally {
            URL::forceRootUrl($root);
        }
    }

    public static function portalUrlFor(User $user): ?string
    {
        if (! $user->tenant_id) {
            return null;
        }

        $tenant = Tenant::query()->find($user->tenant_id);
        if (! $tenant) {
            return null;
        }

        if ($tenant->type === 'school' && $tenant->parent_id) {
            $sahodaya = Tenant::query()->find($tenant->parent_id);

            return TenantDomainSync::publicUrl($sahodaya) ?? TenantDomainSync::publicUrl($tenant);
        }

        return TenantDomainSync::publicUrl($tenant);
    }
}
