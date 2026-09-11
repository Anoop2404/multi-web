<?php

namespace App\Services\Mail;

use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;

class SchoolSiteMailer
{
    public function sendToSchoolContact(Tenant $school, string $subject, string $view, array $data): void
    {
        $contactEmail = $school->settings()->where('key', 'contact')->first()?->value['email'] ?? null;

        $this->sendToAddress($school, $contactEmail, $subject, $view, $data);
    }

    public function sendToAddress(Tenant $school, ?string $recipient, string $subject, string $view, array $data): void
    {
        $recipient = $recipient ?: ($school->settings()->where('key', 'contact')->first()?->value['email'] ?? null);
        if (! $recipient) {
            return;
        }

        $sahodayaId = $school->parent_id;
        if ($sahodayaId) {
            $mailer = SahodayaMailer::for($sahodayaId);
            if ($mailer->isConfigured()) {
                $mailer->sendView($recipient, $subject, $view, $data);

                return;
            }
        }

        Mail::send($view, $data, function ($message) use ($recipient, $subject) {
            $message->to($recipient)->subject($subject);
        });
    }
}
