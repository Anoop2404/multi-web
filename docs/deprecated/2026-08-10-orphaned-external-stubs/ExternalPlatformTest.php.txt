<?php

namespace Tests\Feature\External;

use App\Models\ExternalSahodaya;
use App\Models\ExternalSchool;
use App\Models\FestStateProgram;
use App\Services\External\ExternalAuthService;
use App\Services\External\ExternalConductService;
use App\Services\External\ExternalRegistrationService;
use App\Services\External\ExternalStudentRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ExternalPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');
    }

    public function test_external_user_invitation_and_account_activation(): void
    {
        $authService = new ExternalAuthService();

        $invitation = $authService->createInvitation('coordinator@externalsahodaya.org', 'sahodaya', 'ext-sahodaya-1');
        $this->assertEquals('coordinator@externalsahodaya.org', $invitation['email']);

        $user = $authService->activateAccount($invitation, 'SecretPassword123!');
        $this->assertEquals('coordinator@externalsahodaya.org', $user->email);
    }

    public function test_external_student_registration_and_csv_import(): void
    {
        $program = FestStateProgram::create([
            'title'          => 'Kerala State Kalotsavam 2026',
            'event_type'     => 'kalotsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'published',
        ]);

        $sahodaya = ExternalSahodaya::create([
            'state_program_id' => $program->id,
            'name'             => 'External Sahodaya One',
            'access_code'      => ExternalSahodaya::generateAccessCode(),
            'status'           => 'active',
        ]);

        $school = ExternalSchool::create([
            'external_sahodaya_id' => $sahodaya->id,
            'name'                 => 'External School One',
            'access_code'          => ExternalSchool::generateAccessCode(),
            'status'               => 'active',
        ]);

        $registryService = new ExternalStudentRegistryService();

        $rows = [
            ['name' => 'Alice Smith', 'class' => '10', 'gender' => 'female'],
            ['name' => 'Bob Johnson', 'class' => '12', 'gender' => 'male'],
        ];

        $result = $registryService->importCsv($school, $rows);
        $this->assertEquals(2, $result['imported']);
        $this->assertEmpty($result['errors']);
    }

    public function test_external_registration_payment_proof_and_conduct(): void
    {
        $program = FestStateProgram::create([
            'title'          => 'Kerala State Kalotsavam 2026',
            'event_type'     => 'kalotsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'published',
        ]);

        $sahodaya = ExternalSahodaya::create([
            'state_program_id' => $program->id,
            'name'             => 'External Sahodaya One',
            'access_code'      => ExternalSahodaya::generateAccessCode(),
            'status'           => 'active',
        ]);

        $school = ExternalSchool::create([
            'external_sahodaya_id' => $sahodaya->id,
            'name'                 => 'External School One',
            'access_code'          => ExternalSchool::generateAccessCode(),
            'status'               => 'active',
        ]);

        $regService = new ExternalRegistrationService();
        $registration = $regService->createRegistration($school, '019fea66-9b8d-7361-9828-1f6bbacaf36e', [
            ['student_name' => 'Alice Smith', 'role' => 'performer'],
        ]);
        $this->assertEquals('submitted', $registration['status']);

        $proof = $regService->submitPaymentProof($school, 1000.00, 'UTR12345678', 'proofs/ext_school_1_utr123.pdf');
        $this->assertEquals('pending_verification', $proof['status']);

        $conductService = new ExternalConductService();
        $results = [
            ['item_code' => 'LM01', 'winner_name' => 'Alice Smith', 'school_name' => 'External School One', 'position' => 1, 'score' => 96.0],
        ];

        $conduct = $conductService->processCertifiedOfflineResults($sahodaya, $results, 'proofs/certified_result_sheet.pdf');
        $this->assertEquals(1, $conduct['processed']);
        $this->assertEquals('results_certified', $conduct['status']);
    }
}
