<?php

namespace Tests\Unit\Services\Migration;

use App\Models\Tenant;
use App\Services\Migration\KannurLegacySchoolCredentialSync;
use Tests\TestCase;

class KannurLegacySchoolCredentialSyncTest extends TestCase
{
    private KannurLegacySchoolCredentialSync $sync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sync = app(KannurLegacySchoolCredentialSync::class);
    }

    public function test_resolve_login_email_prefers_school_payload_gmail(): void
    {
        $school = new Tenant([
            'application_payload' => ['school_email' => 'School.Admin@gmail.com'],
        ]);

        $email = $this->sync->resolveLoginEmail($school, [
            'email' => 'legacy@gmail.com',
        ], [
            'email' => 'user@gmail.com',
        ]);

        $this->assertSame('school.admin@gmail.com', $email);
    }

    public function test_resolve_login_email_falls_back_to_legacy_user_email(): void
    {
        $school = new Tenant([
            'application_payload' => [],
        ]);

        $email = $this->sync->resolveLoginEmail($school, [
            'email' => 'not-an-email',
        ], [
            'email' => 'Legacy.User@gmail.com',
        ]);

        $this->assertSame('legacy.user@gmail.com', $email);
    }

    public function test_non_gmail_addresses_are_rejected(): void
    {
        $this->assertFalse($this->sync->isGmailLoginEmail('office@school.edu'));
        $this->assertTrue($this->sync->isGmailLoginEmail('office.school@gmail.com'));
    }
}
