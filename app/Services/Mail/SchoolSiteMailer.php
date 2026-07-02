<?php

namespace App\Services\Mail;

use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;

class SchoolSiteMailer
{
    public function sendToSchoolContact(Tenant $school, string $subject, string $view, array $data): void
    {
        $contactEmail = $school->settings()->where('key', 'contact')->first()?->value['email'] ?? null;
        if (! $contactEmail) {
            return;
        }

        $sahodayaId = $school->parent_id;
        if ($sahodayaId) {
            $mailer = SahodayaMailer::for($sahodayaId);
            if ($mailer->isConfigured()) {
                $mailer->sendView($contactEmail, $subject, $view, $data);

                return;
            }
        }

        Mail::send($view, $data, function ($message) use ($contactEmail, $subject) {
            $message->to($contactEmail)->subject($subject);
        });
    }
}
