<?php

namespace Tests\Feature;

use App\Models\McqExam;
use App\Models\McqQuestionBank;
use App\Models\Tenant;
use App\Services\ExamEngine\ExamEngineCapacityGate;
use App\Services\ExamEngine\ExamEngineRun;
use App\Support\Mcq\McqProductMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\McqFixtures;
use Tests\TestCase;

/** Phase 2/3: Sahodaya model-exam mode, bank sharing, capacity gate. */
class McqSahodayaModelExamTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();
        config(['exam_engine.force_engine_threshold' => 2]);
    }

    private function examsUrl(): string
    {
        return "/sahodaya-admin/{$this->sahodaya->id}/mcq-exams";
    }

    public function test_sahodaya_can_create_fee_free_model_exam(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');

        $this->actingAs($admin)->post($this->examsUrl(), [
            'title' => 'Association Science Model',
            'product_mode' => McqProductMode::SAHODAYA_MODEL,
            'delivery_mode' => 'online',
            'duration_minutes' => 40,
            'eligibility_config' => ['audience' => 'students', 'assignment_type' => 'all', 'gender' => 'open'],
        ])->assertRedirect();

        $exam = McqExam::where('title', 'Association Science Model')->firstOrFail();
        $this->assertTrue($exam->isSahodayaModelExam());
        $this->assertSame('none', $exam->fee_type);
        $this->assertFalse($exam->requiresHallTicket());
        $this->assertSame('partial', $exam->multiMarkingMode());
        $this->assertSame('online', $exam->delivery_mode);
        $this->assertFalse($exam->allowsMemberBanks());
    }

    public function test_model_exam_can_publish_without_fee(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Model draft',
            'status' => 'draft',
            'delivery_mode' => 'online',
            'conductor_level' => 'sahodaya',
            'fee_type' => 'none',
            'exam_type' => 'assessment',
            'settings_json' => McqProductMode::presetSettings(McqProductMode::SAHODAYA_MODEL),
        ]);

        $this->actingAs($admin)->put("{$this->examsUrl()}/{$exam->id}", [
            'title' => 'Model draft',
            'status' => 'published',
            'product_mode' => McqProductMode::SAHODAYA_MODEL,
            'delivery_mode' => 'online',
            'fee_amount' => 0,
            'eligibility_config' => ['audience' => 'students', 'assignment_type' => 'all', 'gender' => 'open'],
        ])->assertSessionHas('success');

        $this->assertSame('published', $exam->fresh()->status);
    }

    public function test_member_school_banks_blocked_unless_allowed(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Paper policy',
            'status' => 'draft',
            'delivery_mode' => 'online',
            'conductor_level' => 'sahodaya',
            'fee_type' => 'none',
            'settings_json' => McqProductMode::presetSettings(McqProductMode::SAHODAYA_MODEL),
        ]);

        $schoolBank = $this->makeBank('active', 'School bank');
        $schoolBank->update(['school_id' => $this->school->id]);
        $this->addSingle($schoolBank);

        $this->actingAs($admin)->post("{$this->examsUrl()}/{$exam->id}/question-banks", ['bank_id' => $schoolBank->id])
            ->assertSessionHas('error');

        $this->actingAs($admin)->put("{$this->examsUrl()}/{$exam->id}", [
            'title' => 'Paper policy',
            'status' => 'draft',
            'product_mode' => McqProductMode::SAHODAYA_MODEL,
            'delivery_mode' => 'online',
            'allow_member_banks' => true,
            'eligibility_config' => ['audience' => 'students', 'assignment_type' => 'all', 'gender' => 'open'],
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post("{$this->examsUrl()}/{$exam->id}/question-banks", ['bank_id' => $schoolBank->id])
            ->assertSessionHas('success');
        $this->assertSame(1, $exam->fresh()->questionBanks()->count());
    }

    public function test_capacity_gate_forces_engine_when_enrolled_over_threshold(): void
    {
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Large cohort',
            'status' => 'published',
            'delivery_mode' => 'online',
            'conductor_level' => 'sahodaya',
            'fee_type' => 'none',
            'settings_json' => McqProductMode::presetSettings(McqProductMode::SAHODAYA_MODEL),
        ]);

        $this->registerStudent($exam);
        $this->registerStudent($exam);

        $this->assertFalse(ExamEngineRun::usesEngine($exam));
        $result = app(ExamEngineCapacityGate::class)->ensureEnginePreferred($exam->fresh());
        $this->assertTrue($result['forced']);
        $this->assertTrue(ExamEngineRun::usesEngine($exam->fresh()));
    }

    public function test_inactive_member_school_flagged_in_membership_health(): void
    {
        $inactive = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Inactive Member',
            'type' => 'school',
            'parent_id' => $this->sahodaya->id,
            'is_active' => false,
            'membership_status' => 'approved',
        ]);

        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Health check',
            'status' => 'published',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'settings_json' => McqProductMode::presetSettings(McqProductMode::SAHODAYA_MODEL),
        ]);

        $problems = app(\App\Services\Mcq\McqMembershipHealthService::class)->problemSchools($exam);
        $this->assertTrue($problems->contains(fn ($row) => $row['school_id'] === $inactive->id && in_array('inactive', $row['flags'], true)));
    }
}
