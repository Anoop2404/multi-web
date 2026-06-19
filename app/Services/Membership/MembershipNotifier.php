<?php

namespace App\Services\Membership;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\SahodayaMailer;
use App\Support\TenantDomainSync;

class MembershipNotifier
{
    public function schoolApplicationSubmitted(Tenant $school): void
    {
        $this->notifySahodayaAdmins($school->parent_id, 'School Registration Submitted', [
            'school' => $school->name,
            'message' => "A new school \"{$school->name}\" has submitted a membership application.",
        ]);
    }

    public function schoolCredentialsIssued(User $user, string $plainPassword, Tenant $school): void
    {
        $sahodaya = $school->parent_id ? Tenant::find($school->parent_id) : null;
        $portal = $sahodaya ? TenantDomainSync::publicUrl($sahodaya) : null;
        $loginUrl = $portal ? rtrim($portal, '/').'/login' : url('/login');
        $body = <<<TEXT
Your school "{$school->name}" has been registered with {$school->parent?->name}.

Login email (Gmail): {$user->email}
Temporary password: {$plainPassword}

1. Open {$loginUrl}
2. Verify your Gmail address using the link we sent separately
3. Sign in with the credentials above

You may change your password after logging in. Keep this email safe.
TEXT;

        $this->mailerFor($school->parent_id)->sendRaw(
            $user->email,
            'School Portal Login — Verify Gmail & Sign In',
            $body,
        );
    }

    public function schoolApproved(Tenant $school): void
    {
        $email = $school->application_payload['school_email']
            ?? $school->application_payload['contact_email']
            ?? null;
        if ($email) {
            $this->mailerFor($school->parent_id)->sendRaw(
                $email,
                'School Registration Approved',
                "Your school \"{$school->name}\" has been approved. You may now log in.",
            );
        }
    }

    public function schoolRejected(Tenant $school, string $reason): void
    {
        $email = $school->application_payload['school_email']
            ?? $school->application_payload['contact_email']
            ?? null;
        if ($email) {
            $this->mailerFor($school->parent_id)->sendRaw(
                $email,
                'School Registration Rejected',
                "Your school application was rejected. Reason: {$reason}",
            );
        }
    }

    public function dataSubmitted(Tenant $school, string $academicYear): void
    {
        $this->notifySahodayaAdmins($school->parent_id, 'Student/Teacher Data Submitted', [
            'school' => $school->name,
            'year'   => $academicYear,
            'message' => "{$school->name} submitted annual data for {$academicYear}.",
        ]);
    }

    public function dataApproved(Tenant $school, string $academicYear): void
    {
        $this->notifySchoolAdmins($school, "Annual data for {$academicYear} has been approved.");
    }

    public function dataRejected(Tenant $school, string $academicYear, string $reason): void
    {
        $this->notifySchoolAdmins($school, "Annual data for {$academicYear} was rejected. Reason: {$reason}");
    }

    public function paymentSubmitted(
        Tenant $school,
        string $academicYear,
        ?float $amount = null,
        ?string $transactionRef = null,
        ?string $paymentMethod = null,
    ): void {
        $sahodayaId = $school->parent_id;
        $paymentsUrl = url("/sahodaya-admin/{$sahodayaId}/membership/payments");

        $lines = [
            "{$school->name} uploaded membership payment proof for {$academicYear}.",
            '',
            'Please review and verify the payment:',
            $paymentsUrl,
        ];

        if ($amount !== null) {
            $lines[] = '';
            $lines[] = 'Amount: ₹'.number_format($amount, 2);
        }
        if ($paymentMethod) {
            $lines[] = "Method: {$paymentMethod}";
        }
        if ($transactionRef) {
            $lines[] = "Reference: {$transactionRef}";
        }

        $this->notifySahodayaRecipients(
            $sahodayaId,
            "Payment proof submitted — {$school->name}",
            implode("\n", $lines),
        );
    }

    public function paymentVerified(Tenant $school, string $academicYear, string $membershipNo): void
    {
        $this->notifySchoolAdmins($school, "Payment verified. Membership {$membershipNo} for {$academicYear} is complete.");
    }

    public function paymentRejected(Tenant $school, string $academicYear, string $reason): void
    {
        $this->notifySchoolAdmins($school, "Payment for {$academicYear} was rejected. Reason: {$reason}");
    }

    public function registrationCompleted(Tenant $school, string $academicYear, string $membershipNo): void
    {
        $this->notifySchoolAdmins($school, "Annual membership for {$academicYear} is complete. Membership No: {$membershipNo}");
    }

    private function notifySahodayaAdmins(string $sahodayaId, string $subject, array $context): void
    {
        $this->notifySahodayaRecipients($sahodayaId, $subject, $context['message'] ?? $subject);
    }

    private function notifySahodayaRecipients(string $sahodayaId, string $subject, string $message): void
    {
        $mailer = $this->mailerFor($sahodayaId);
        $emails = User::where('tenant_id', $sahodayaId)->pluck('email');

        $profileEmail = $mailer->contactEmail();
        if ($profileEmail) {
            $emails->push($profileEmail);
        }

        $mailer->sendRawToMany(
            $emails->unique()->filter()->all(),
            $subject,
            $message,
        );
    }

    private function notifySchoolAdmins(Tenant $school, string $message): void
    {
        $admins = User::where('tenant_id', $school->id)->get();
        $recipients = $admins->pluck('email')->all();

        $email = $school->application_payload['school_email']
            ?? $school->application_payload['contact_email']
            ?? null;
        if ($email && ! in_array($email, $recipients, true)) {
            $recipients[] = $email;
        }

        $this->mailerFor($school->parent_id)->sendRawToMany(
            $recipients,
            'Membership Registration Update',
            $message,
        );
    }

    private function mailerFor(?string $sahodayaId): SahodayaMailer
    {
        return SahodayaMailer::for($sahodayaId ?? '');
    }
}
