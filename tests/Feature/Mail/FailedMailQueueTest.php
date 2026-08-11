<?php

namespace Tests\Feature\Mail;

use App\Models\FailedEmailLog;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\SahodayaMailer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FailedMailQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_email_is_logged_to_queue_when_sending_fails(): void
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Mail Test Sahodaya',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'MLT',
        ]);

        $mailer = SahodayaMailer::for($sahodaya->id);

        $log = $mailer->logFailedMail(
            to: 'test@school.com',
            subject: 'Test Subject',
            mailType: 'raw',
            view: null,
            payload: ['body' => 'Hello World'],
            errorMessage: 'ZeptoMail API error: Request Denied',
            recipientName: 'Test School',
        );

        $this->assertDatabaseHas('failed_email_logs', [
            'id' => $log->id,
            'sahodaya_id' => $sahodaya->id,
            'recipient_email' => 'test@school.com',
            'status' => 'pending',
            'error_message' => 'ZeptoMail API error: Request Denied',
        ]);
    }

    public function test_sahodaya_admin_can_view_failed_email_queue(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Queue Admin Sahodaya',
            'is_active' => true,
        ]);

        $admin = User::create([
            'tenant_id' => $sahodaya->id,
            'name' => 'Sahodaya Admin',
            'email' => 'queue_admin@sahodaya.test',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('sahodaya_admin');

        FailedEmailLog::create([
            'sahodaya_id' => $sahodaya->id,
            'recipient_email' => 'failed@school.com',
            'recipient_name' => 'Failed School',
            'subject' => 'Verify Email',
            'mail_type' => 'verification',
            'error_message' => 'ZeptoMail API error: Request Denied',
            'status' => 'pending',
            'attempts' => 1,
            'last_attempted_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/settings/failed-mails");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Sahodaya/Settings/FailedMails')
            ->has('logs.data', 1)
            ->where('summary.pending_count', 1)
        );
    }
}
