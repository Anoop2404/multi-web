<?php

namespace Tests\Feature;

use App\Models\McqExam;
use App\Support\Mcq\McqProductMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\McqFixtures;
use Tests\TestCase;

/** Regression coverage for QA report fixes (session-monitor alias, etc.). */
class McqQaUxFixesTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();
    }

    public function test_session_monitor_alias_redirects_to_session(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Session alias',
            'status' => 'published',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'settings_json' => McqProductMode::presetSettings(McqProductMode::SAHODAYA_MODEL),
        ]);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$exam->id}/session-monitor")
            ->assertRedirect("/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$exam->id}/session");
    }

    public function test_inertia_page_sources_exist_for_qa_critical_routes(): void
    {
        $pages = [
            resource_path('js/Pages/Admin/School/ModelExams/Index.vue'),
            resource_path('js/Pages/Admin/School/ModelExams/QuestionBanks.vue'),
            resource_path('js/Pages/Admin/Portal/Student/McqSystemCheck.vue'),
            resource_path('js/Layouts/PortalLayout.vue'),
            resource_path('js/Components/mcq/McqQuestionEditor.vue'),
        ];

        foreach ($pages as $path) {
            $this->assertFileExists($path, "Missing Inertia/Vite page source: {$path}");
        }

        $layout = file_get_contents(resource_path('js/Layouts/PortalLayout.vue'));
        $this->assertStringNotContainsString("default: 'Teacher Portal'", $layout);
        $this->assertStringContainsString('brandTitle', $layout);

        $editor = file_get_contents(resource_path('js/Components/mcq/McqQuestionEditor.vue'));
        $this->assertStringContainsString('mcq:bank-draft:', $editor);
        $this->assertStringContainsString('Draft saved locally', $editor);
    }
}
