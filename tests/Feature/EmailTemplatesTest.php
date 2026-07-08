<?php

namespace Tests\Feature;

use App\Models\AdmissionEnquiry;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PortalVerifyEmail;
use App\Support\Mail\EmailBranding;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_membership_email_templates_render_without_errors(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Central Sahodaya',
            'domain'    => 'malappuramcentralsahodaya.org',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'     => $sahodaya->id,
            'contact_email' => 'office@malappuramcentralsahodaya.org',
        ]);

        $school = Tenant::create([
            'id'                  => (string) Str::uuid(),
            'type'                => 'school',
            'name'                => 'Demo Public School',
            'parent_id'           => $sahodaya->id,
            'school_prefix'       => 'DPS',
            'membership_status'   => 'pending',
            'is_active'           => true,
            'application_payload' => [
                'school_email'     => 'demo.school@gmail.com',
                'cbse_affiliation' => '930319',
                'phone'            => '9876543210',
                'highest_class'    => 'Class 12',
            ],
        ]);

        $user = User::factory()->create([
            'tenant_id' => $school->id,
            'email'     => 'demo.school@gmail.com',
        ]);

        $branding = EmailBranding::forTenant($sahodaya);
        $loginUrl = EmailBranding::schoolLoginUrl($sahodaya);
        $dashboardUrl = EmailBranding::schoolAdminUrl($sahodaya, $school);
        $reviewUrl = EmailBranding::sahodayaAdminUrl($sahodaya, 'schools?status=pending');
        $paymentsUrl = EmailBranding::sahodayaAdminUrl($sahodaya, 'membership/payments');

        $templates = [
            'emails.verify-email' => array_merge($branding, [
                'headerTitle' => 'Gmail Verification',
                'userName' => $user->name,
                'schoolName' => $school->name,
                'verificationUrl' => 'https://malappuramcentralsahodaya.org/email/verify/1/abc',
                'verificationMins' => 60,
            ]),
            'emails.test-mail' => array_merge($branding, [
                'headerTitle' => 'SMTP Test',
            ]),
            'emails.membership.school-credentials' => array_merge($branding, [
                'headerTitle' => 'School Portal Access',
                'school' => $school,
                'user' => $user,
                'plainPassword' => 'TempPass123!',
                'loginUrl' => $loginUrl,
            ]),
            'emails.membership.school-approved' => array_merge($branding, [
                'school' => $school,
                'loginUrl' => $loginUrl,
            ]),
            'emails.membership.school-rejected' => array_merge($branding, [
                'school' => $school,
                'reason' => 'Incomplete CBSE affiliation details.',
            ]),
            'emails.membership.school-application-submitted' => array_merge($branding, [
                'school' => $school,
                'reviewUrl' => $reviewUrl,
                'applicationDetails' => [
                    'School name' => $school->name,
                    'School code' => 'DPS',
                    'Contact email' => 'demo.school@gmail.com',
                ],
            ]),
            'emails.membership.payment-submitted' => array_merge($branding, [
                'school' => $school,
                'academicYear' => '2025-26',
                'amount' => 5000.0,
                'paymentMethod' => 'UPI',
                'transactionRef' => 'UTR123456',
                'paymentsUrl' => $paymentsUrl,
            ]),
            'emails.membership.registration-complete' => array_merge($branding, [
                'title' => 'Welcome to the Sahodaya network',
                'body' => 'Welcome message body.',
                'academicYear' => '2025-26',
                'membershipNo' => 'MAL/DPS/26/0001',
                'firstApproval' => true,
                'loginUrl' => $loginUrl,
                'dashboardUrl' => $dashboardUrl,
            ]),
            'emails.membership.generic-admin' => array_merge($branding, [
                'title' => 'Review annual submission',
                'body' => 'Demo Public School submitted data.',
                'details' => ['School' => $school->name, 'Academic year' => '2025-26'],
                'actionUrl' => $reviewUrl,
                'actionLabel' => 'Review submission',
            ]),
            'emails.membership.generic-school' => array_merge($branding, [
                'title' => 'Payment rejected',
                'body' => 'Please upload a valid proof again.',
                'reason' => 'Screenshot was unclear.',
                'reasonTitle' => 'Rejection reason',
                'alertVariant' => 'danger',
                'actionUrl' => EmailBranding::schoolAdminUrl($sahodaya, $school, 'registration'),
                'actionLabel' => 'Upload new proof',
            ]),
        ];

        foreach ($templates as $view => $data) {
            $html = View::make($view, $data)->render();
            $this->assertNotSame('', trim($html), "Template {$view} rendered empty HTML.");
            $this->assertStringContainsString('Malappuram Central Sahodaya', $html, "Template {$view} missing Sahodaya name.");
        }
    }

    public function test_portal_urls_use_sahodaya_domain_in_notifier_links(): void
    {
        $sahodaya = Tenant::create([
            'id'     => (string) Str::uuid(),
            'type'   => 'sahodaya',
            'name'   => 'Test Sahodaya',
            'domain' => 'testsahodaya.org',
        ]);

        $school = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'Link School',
            'parent_id' => $sahodaya->id,
        ]);

        $login = EmailBranding::schoolLoginUrl($sahodaya);
        $dashboard = EmailBranding::schoolAdminUrl($sahodaya, $school);
        $admin = EmailBranding::sahodayaAdminUrl($sahodaya, 'membership/payments');

        $this->assertStringStartsWith('https://testsahodaya.org/', $login);
        $this->assertStringContainsString('/school-admin/'.$school->id, $dashboard);
        $this->assertStringContainsString('/sahodaya-admin/'.$sahodaya->id.'/membership/payments', $admin);
    }

    public function test_verify_email_notification_renders_html_body(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'     => (string) Str::uuid(),
            'type'   => 'sahodaya',
            'name'   => 'Verify Sahodaya',
            'domain' => 'verify.test',
        ]);

        $school = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'Verify School',
            'parent_id' => $sahodaya->id,
        ]);

        $admin = User::factory()->create(['tenant_id' => $school->id]);
        $admin->assignRole('school_admin');

        $mail = (new PortalVerifyEmail)->toMail($admin);
        $html = View::make($mail->view, $mail->viewData)->render();

        $this->assertStringContainsString('Verify your Gmail address', $html);
        $this->assertStringContainsString('Verify Gmail &amp; open portal', $html);
    }

    public function test_public_form_emails_render_with_branding(): void
    {
        $school = Tenant::create([
            'id'   => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Public School',
        ]);

        $branding = EmailBranding::forTenant($school);

        $enquiry = new AdmissionEnquiry([
            'student_name' => 'Student B',
            'dob' => '2012-05-05',
            'class_applying' => '1',
            'academic_year' => '2025-26',
            'parent_name' => 'Parent B',
            'phone' => '8888888888',
            'email' => 'parent@gmail.com',
            'address' => 'Kerala',
            'message' => 'Interested in admission',
        ]);
        $enquiry->created_at = now();

        View::make('emails.admission-enquiry', array_merge($branding, [
            'enquiry' => $enquiry,
            'school' => $school,
            'headerTitle' => 'New Admission Enquiry',
            'headerSubtitle' => $school->name,
        ]))->render();

        $this->assertTrue(true);
    }
}
