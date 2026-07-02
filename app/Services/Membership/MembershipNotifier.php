<?php

namespace App\Services\Membership;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\SahodayaMailer;
use App\Support\Mail\EmailBranding;

class MembershipNotifier
{
    public function schoolApplicationSubmitted(Tenant $school): void
    {
        $sahodaya = $this->sahodayaFor($school);
        $payload = $school->application_payload ?? [];

        $this->notifySahodayaRecipients(
            $school->parent_id,
            'New school application — '.$school->name,
            'emails.membership.school-application-submitted',
            [
                'headerTitle'    => 'New School Application',
                'headerSubtitle' => $school->name,
                'headerEyebrow'  => 'Membership',
                'school'         => $school,
                'reviewUrl'      => EmailBranding::sahodayaAdminUrl($sahodaya, 'schools?status=pending'),
                'applicationDetails' => array_filter([
                    'School name'        => $school->name,
                    'School code'        => $school->school_prefix,
                    'CBSE affiliation'   => $payload['cbse_affiliation'] ?? $payload['affiliation_number'] ?? null,
                    'Contact email'      => $payload['school_email'] ?? $payload['contact_email'] ?? null,
                    'Phone'              => $payload['phone'] ?? $payload['contact_phone'] ?? null,
                    'Highest class'      => $payload['highest_class'] ?? null,
                ]),
            ],
        );
    }

    public function schoolCredentialsIssued(User $user, string $plainPassword, Tenant $school): void
    {
        $sahodaya = $this->sahodayaFor($school);
        $loginUrl = EmailBranding::schoolLoginUrl($sahodaya);

        $this->mailerFor($school->parent_id)->sendView(
            $user->email,
            'School Portal Login — Verify Gmail & Sign In',
            'emails.membership.school-credentials',
            [
                'headerTitle'    => 'School Portal Access',
                'headerSubtitle' => $school->name,
                'headerEyebrow'  => 'Welcome',
                'school'         => $school,
                'user'           => $user,
                'plainPassword'  => $plainPassword,
                'loginUrl'       => $loginUrl,
            ],
        );
    }

    public function schoolApproved(Tenant $school): void
    {
        $email = $this->schoolContactEmail($school);
        if (! $email) {
            return;
        }

        $sahodaya = $this->sahodayaFor($school);
        $loginUrl = EmailBranding::schoolLoginUrl($sahodaya);

        $this->mailerFor($school->parent_id)->sendView(
            $email,
            'School Membership Approved — '.$school->name,
            'emails.membership.school-approved',
            [
                'headerTitle'    => 'Membership Approved',
                'headerSubtitle' => $school->name,
                'headerEyebrow'  => 'Welcome',
                'school'         => $school,
                'loginUrl'       => $loginUrl,
            ],
        );
    }

    public function schoolRejected(Tenant $school, string $reason): void
    {
        $email = $this->schoolContactEmail($school);
        if (! $email) {
            return;
        }

        $this->mailerFor($school->parent_id)->sendView(
            $email,
            'School Application Rejected — '.$school->name,
            'emails.membership.school-rejected',
            [
                'headerTitle'    => 'Application Not Approved',
                'headerSubtitle' => $school->name,
                'headerEyebrow'  => 'Membership',
                'school'         => $school,
                'reason'         => $reason,
            ],
        );
    }

    public function dataSubmitted(Tenant $school, string $academicYear): void
    {
        $sahodaya = $this->sahodayaFor($school);

        $this->notifySahodayaRecipients(
            $school->parent_id,
            "Annual data submitted — {$school->name}",
            'emails.membership.generic-admin',
            [
                'headerTitle'    => 'Annual Data Submitted',
                'headerSubtitle' => $school->name,
                'headerEyebrow'  => $academicYear,
                'title'          => 'Review annual submission',
                'body'           => "{$school->name} submitted student and/or teacher data for {$academicYear}. Please review the submission in the Sahodaya admin panel.",
                'details'        => [
                    'School'        => $school->name,
                    'Academic year' => $academicYear,
                ],
                'actionUrl'      => EmailBranding::sahodayaAdminUrl($sahodaya, 'membership/submissions'),
                'actionLabel'    => 'Review submission',
            ],
        );
    }

    public function dataApproved(Tenant $school, string $academicYear): void
    {
        $sahodaya = $this->sahodayaFor($school);

        $this->notifySchoolAdmins(
            $school,
            "Annual data approved — {$academicYear}",
            'emails.membership.generic-school',
            [
                'headerTitle'    => 'Annual Data Approved',
                'headerSubtitle' => $school->name,
                'headerEyebrow'  => $academicYear,
                'title'          => 'Submission approved',
                'body'           => "Your annual data submission for {$academicYear} has been approved by {$sahodaya?->name}. You can continue with membership payment.",
                'actionUrl'      => EmailBranding::schoolAdminUrl($sahodaya, $school, 'registration'),
                'actionLabel'    => 'Continue registration',
                'actionVariant'  => 'success',
            ],
        );
    }

    public function dataRejected(Tenant $school, string $academicYear, string $reason): void
    {
        $sahodaya = $this->sahodayaFor($school);

        $this->notifySchoolAdmins(
            $school,
            "Annual data rejected — {$academicYear}",
            'emails.membership.generic-school',
            [
                'headerTitle'    => 'Annual Data Rejected',
                'headerSubtitle' => $school->name,
                'headerEyebrow'  => $academicYear,
                'title'          => 'Submission needs correction',
                'body'           => "Your annual data submission for {$academicYear} was rejected. Please review the reason below, make corrections, and resubmit.",
                'reason'         => $reason,
                'reasonTitle'    => 'Rejection reason',
                'alertVariant'   => 'danger',
                'actionUrl'      => EmailBranding::schoolAdminUrl($sahodaya, $school, 'registration'),
                'actionLabel'    => 'Fix and resubmit',
                'actionVariant'  => 'danger',
            ],
        );
    }

    public function paymentSubmitted(
        Tenant $school,
        string $academicYear,
        ?float $amount = null,
        ?string $transactionRef = null,
        ?string $paymentMethod = null,
    ): void {
        $sahodaya = $this->sahodayaFor($school);

        $this->notifySahodayaRecipients(
            $school->parent_id,
            "Payment proof submitted — {$school->name}",
            'emails.membership.payment-submitted',
            [
                'headerTitle'    => 'Payment Proof Received',
                'headerSubtitle' => $school->name,
                'headerEyebrow'  => $academicYear,
                'school'         => $school,
                'academicYear'   => $academicYear,
                'amount'         => $amount,
                'transactionRef' => $transactionRef,
                'paymentMethod'  => $paymentMethod,
                'paymentsUrl'    => EmailBranding::sahodayaAdminUrl($sahodaya, 'membership/payments'),
            ],
        );
    }

    public function paymentVerified(Tenant $school, string $academicYear, string $membershipNo): void
    {
        $sahodaya = $this->sahodayaFor($school);

        $this->notifySchoolAdmins(
            $school,
            "Payment verified — Membership {$membershipNo}",
            'emails.membership.generic-school',
            [
                'headerTitle'    => 'Payment Verified',
                'headerSubtitle' => $school->name,
                'headerEyebrow'  => $academicYear,
                'title'          => 'Membership payment approved',
                'body'           => "Your membership payment for {$academicYear} has been verified by {$sahodaya?->name}. Your membership number is shown below.",
                'details'        => [
                    'Academic year'  => $academicYear,
                    'Membership No.' => $membershipNo,
                    'Status'         => 'Payment verified',
                ],
                'actionUrl'      => EmailBranding::schoolAdminUrl($sahodaya, $school, 'registration'),
                'actionLabel'    => 'View registration',
                'actionVariant'  => 'success',
            ],
        );
    }

    public function paymentRejected(Tenant $school, string $academicYear, string $reason): void
    {
        $sahodaya = $this->sahodayaFor($school);

        $this->notifySchoolAdmins(
            $school,
            "Payment rejected — {$academicYear}",
            'emails.membership.generic-school',
            [
                'headerTitle'    => 'Payment Rejected',
                'headerSubtitle' => $school->name,
                'headerEyebrow'  => $academicYear,
                'title'          => 'Payment proof not accepted',
                'body'           => "Your payment proof for {$academicYear} was rejected by {$sahodaya?->name}. Please upload a valid proof again.",
                'reason'         => $reason,
                'reasonTitle'    => 'Rejection reason',
                'alertVariant'   => 'danger',
                'actionUrl'      => EmailBranding::schoolAdminUrl($sahodaya, $school, 'registration'),
                'actionLabel'    => 'Upload new proof',
                'actionVariant'  => 'danger',
            ],
        );
    }

    public function registrationCompleted(
        Tenant $school,
        string $academicYear,
        string $membershipNo,
        bool $firstMembershipApproval = false,
        ?string $receiptHtml = null,
        ?string $receiptNo = null,
    ): void {
        $sahodaya = $this->sahodayaFor($school);

        $body = $firstMembershipApproval
            ? "Welcome! Your school's membership with {$sahodaya?->name} has been approved and your {$academicYear} annual registration is complete."
            : "Your {$academicYear} annual Sahodaya membership registration is complete. Payment has been verified and your membership is now active.";

        if ($receiptHtml) {
            $body .= ' Your official membership fee receipt is attached to this email.';
        }

        $viewData = [
            'headerTitle'    => 'Membership Complete',
            'headerSubtitle' => $school->name,
            'headerEyebrow'  => $academicYear,
            'title'          => $firstMembershipApproval ? 'Welcome to the Sahodaya network' : 'Annual membership active',
            'body'           => $body,
            'academicYear'   => $academicYear,
            'membershipNo'   => $membershipNo,
            'firstApproval'  => $firstMembershipApproval,
            'receiptNo'      => $receiptNo,
            'loginUrl'       => EmailBranding::schoolLoginUrl($sahodaya),
            'dashboardUrl'   => EmailBranding::schoolAdminUrl($sahodaya, $school),
        ];

        $attachments = [];
        if ($receiptHtml) {
            $filename = 'membership-receipt-'.($receiptNo ?: $membershipNo).'.html';
            $attachments[] = [
                'content' => $receiptHtml,
                'name'    => $filename,
                'mime'    => 'text/html',
            ];
        }

        $this->notifySchoolAdminsWithAttachments(
            $school,
            'Membership complete — '.$membershipNo.($receiptNo ? " (Receipt {$receiptNo})" : ''),
            'emails.membership.registration-complete',
            $viewData,
            $attachments,
        );
    }

    /** @param  array<string, mixed>  $viewData */
    private function notifySahodayaRecipients(string $sahodayaId, string $subject, string $view, array $viewData): void
    {
        $mailer = $this->mailerFor($sahodayaId);
        $emails = User::where('tenant_id', $sahodayaId)->pluck('email');

        $profileEmail = $mailer->contactEmail();
        if ($profileEmail) {
            $emails->push($profileEmail);
        }

        $mailer->sendViewToMany(
            $emails->unique()->filter()->all(),
            $subject,
            $view,
            $viewData,
        );
    }

    /** @param  array<string, mixed>  $viewData  @param  list<array{content: string, name: string, mime?: string}>  $attachments */
    private function notifySchoolAdminsWithAttachments(
        Tenant $school,
        string $subject,
        string $view,
        array $viewData,
        array $attachments = [],
    ): void {
        $admins = User::where('tenant_id', $school->id)->get();
        $recipients = $admins->pluck('email')->all();

        $email = $this->schoolContactEmail($school);
        if ($email && ! in_array($email, $recipients, true)) {
            $recipients[] = $email;
        }

        $mailer = $this->mailerFor($school->parent_id);

        if ($attachments) {
            $mailer->sendViewToManyWithAttachments($recipients, $subject, $view, $viewData, $attachments);
        } else {
            $mailer->sendViewToMany($recipients, $subject, $view, $viewData);
        }
    }

    /** @param  array<string, mixed>  $viewData */
    private function notifySchoolAdmins(Tenant $school, string $subject, string $view, array $viewData): void
    {
        $this->notifySchoolAdminsWithAttachments($school, $subject, $view, $viewData);
    }

    private function sahodayaFor(Tenant $school): ?Tenant
    {
        return $school->parent_id
            ? Tenant::query()->find($school->parent_id)
            : null;
    }

    private function schoolContactEmail(Tenant $school): ?string
    {
        return $school->application_payload['school_email']
            ?? $school->application_payload['contact_email']
            ?? null;
    }

    private function mailerFor(?string $sahodayaId): SahodayaMailer
    {
        return SahodayaMailer::for($sahodayaId ?? '');
    }

    public function reminderWindowClosing(Tenant $school, string $academicYear, int $daysLeft): void
    {
        $email = $this->schoolContactEmail($school);
        if (! $email) {
            return;
        }

        $this->mailerFor($school->parent_id)->sendView(
            $email,
            'Membership registration closing soon — '.$school->name,
            'emails.membership.generic-school',
            [
                'title'          => 'Registration closing soon',
                'body'           => "Annual membership registration for {$academicYear} closes in {$daysLeft} day(s). Please begin registration in the school portal.",
            ],
        );
    }

    public function reminderPaymentDue(Tenant $school, string $academicYear, float $amount): void
    {
        $email = $this->schoolContactEmail($school);
        if (! $email) {
            return;
        }

        $this->mailerFor($school->parent_id)->sendView(
            $email,
            'Membership payment due — '.$school->name,
            'emails.membership.generic-school',
            [
                'title'          => 'Payment due',
                'body'           => "Membership fee of ₹".number_format($amount, 2)." for {$academicYear} is pending. Please upload payment proof in the school portal.",
            ],
        );
    }
}
