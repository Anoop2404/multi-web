<?php

namespace Tests\Feature;

use App\Models\ClassCategory;
use App\Models\MasterClass;
use App\Models\McqExam;
use App\Models\McqQuestionBank;
use App\Services\Mcq\McqQuestionBankCopyService;
use App\Support\Mcq\McqProductMode;
use App\Support\Mcq\McqQuestionBankScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\McqFixtures;
use Tests\TestCase;

class McqQuestionBankClassScopeTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    private ClassCategory $category;

    private MasterClass $masterClass;

    private MasterClass $otherClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();

        $this->category = ClassCategory::create([
            'sahodaya_id' => $this->sahodaya->id,
            'code'        => 'PRIM',
            'label'       => 'Primary',
            'is_active'   => true,
        ]);
        $otherCategory = ClassCategory::create([
            'sahodaya_id' => $this->sahodaya->id,
            'code'        => 'SEN',
            'label'       => 'Senior',
            'is_active'   => true,
        ]);
        $this->masterClass = MasterClass::create([
            'sahodaya_id'       => $this->sahodaya->id,
            'class_category_id' => $this->category->id,
            'name'              => 'Class 5',
            'is_active'         => true,
        ]);
        $this->otherClass = MasterClass::create([
            'sahodaya_id'       => $this->sahodaya->id,
            'class_category_id' => $otherCategory->id,
            'name'              => 'Class 10',
            'is_active'         => true,
        ]);
    }

    public function test_school_can_create_bank_scoped_to_class_and_category(): void
    {
        $admin = $this->userWithRole('school_admin', $this->school->id);

        $this->actingAs($admin)->post("/school-admin/{$this->school->id}/model-exams/question-banks", [
            'title'             => 'Class 5 Science',
            'subject'           => 'Science',
            'class_category_id' => $this->category->id,
            'master_class_id'   => $this->masterClass->id,
        ])->assertRedirect();

        $bank = McqQuestionBank::where('title', 'Class 5 Science')->firstOrFail();
        $this->assertSame($this->school->id, $bank->school_id);
        $this->assertSame($this->category->id, (int) $bank->class_category_id);
        $this->assertSame($this->masterClass->id, (int) $bank->master_class_id);
    }

    public function test_paper_builder_filters_banks_by_exam_class_eligibility(): void
    {
        $admin = $this->userWithRole('school_admin', $this->school->id);

        $exam = McqExam::create([
            'tenant_id'          => $this->sahodaya->id,
            'school_id'          => $this->school->id,
            'conductor_level'    => 'school',
            'title'              => 'Class 5 model',
            'status'             => 'draft',
            'delivery_mode'      => 'online',
            'fee_type'           => 'none',
            'settings_json'      => McqProductMode::presetSettings(McqProductMode::SCHOOL_MODEL),
            'eligibility_config' => [
                'audience'        => 'students',
                'assignment_type' => 'class',
                'master_class_ids'=> [$this->masterClass->id],
            ],
        ]);

        $match = $this->makeBank('active', 'Match class');
        $match->update([
            'school_id'         => $this->school->id,
            'master_class_id'   => $this->masterClass->id,
            'class_category_id' => $this->category->id,
        ]);
        $this->addSingle($match);

        $mismatch = $this->makeBank('active', 'Wrong class');
        $mismatch->update([
            'school_id'         => $this->school->id,
            'master_class_id'   => $this->otherClass->id,
            'class_category_id' => $this->otherClass->class_category_id,
        ]);
        $this->addSingle($mismatch);

        $broad = $this->makeBank('active', 'Unscoped');
        $broad->update(['school_id' => $this->school->id]);
        $this->addSingle($broad);

        $this->assertTrue(McqQuestionBankScope::matchesExam($match->fresh(), $exam));
        $this->assertFalse(McqQuestionBankScope::matchesExam($mismatch->fresh(), $exam));
        $this->assertTrue(McqQuestionBankScope::matchesExam($broad->fresh(), $exam));

        $this->actingAs($admin)->post("/school-admin/{$this->school->id}/model-exams/{$exam->id}/paper/banks", [
            'bank_id' => $mismatch->id,
        ])->assertSessionHas('error');

        $this->actingAs($admin)->post("/school-admin/{$this->school->id}/model-exams/{$exam->id}/paper/banks", [
            'bank_id' => $match->id,
        ])->assertSessionHas('success');

        $this->assertSame(1, $exam->fresh()->questionBanks()->count());
    }

    public function test_school_can_copy_association_bank_and_contribute_back(): void
    {
        $admin = $this->userWithRole('school_admin', $this->school->id);
        $assoc = $this->makeBank('active', 'Sahodaya Science');
        $this->addSingle($assoc);

        $this->actingAs($admin)->post(
            "/school-admin/{$this->school->id}/model-exams/question-banks/{$assoc->id}/copy-to-school"
        )->assertRedirect();

        $schoolCopy = McqQuestionBank::where('source_bank_id', $assoc->id)
            ->where('school_id', $this->school->id)
            ->firstOrFail();
        $this->assertSame('draft', $schoolCopy->status);
        $this->assertSame(1, $schoolCopy->questions()->count());

        $schoolCopy->update(['status' => 'active']);

        $this->actingAs($admin)->post(
            "/school-admin/{$this->school->id}/model-exams/question-banks/{$schoolCopy->id}/contribute"
        )->assertSessionHas('success');

        $contributed = McqQuestionBank::where('source_bank_id', $schoolCopy->id)
            ->whereNull('school_id')
            ->where('contributed_by_school_id', $this->school->id)
            ->firstOrFail();
        $this->assertSame('draft', $contributed->status);
        $this->assertSame(1, $contributed->questions()->count());
    }

    public function test_copy_service_rejects_non_ready_source(): void
    {
        $draft = $this->makeBank('draft', 'Draft only');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(McqQuestionBankCopyService::class)->copyIntoSchool($draft, $this->school, 1);
    }
}
