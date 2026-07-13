<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEligibilityRule;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\Student;
use App\Services\Events\FestEligibilityRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestEligibilityRuleEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('fest_eligibility_rules')) {
            $this->markTestSkipped('fest_eligibility_rules not migrated.');
        }
    }

    public function test_empty_rules_pass(): void
    {
        $event = new FestEvent(['id' => 1, 'tenant_id' => (string) Str::uuid()]);
        $item = new FestEventItem(['id' => 1]);
        $student = new Student(['gender' => 'male']);

        $this->assertSame([], app(FestEligibilityRuleEngine::class)->validateStudent($student, $event, $item));
    }

    public function test_gender_rule_rejects_mismatch(): void
    {
        $tenantId = (string) Str::uuid();
        $event = FestEvent::create([
            'tenant_id' => $tenantId,
            'title' => 'Robotics',
            'event_type' => 'custom',
            'level_round' => 'sahodaya',
            'status' => 'draft',
        ]);
        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Line follow',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);

        FestEligibilityRule::create([
            'tenant_id' => $tenantId,
            'scope_type' => FestEligibilityRule::SCOPE_EVENT,
            'scope_id' => $event->id,
            'rule_type' => 'gender',
            'operator' => 'in',
            'value_json' => ['in' => ['female']],
            'logic_group' => 0,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $student = new Student(['gender' => 'male']);
        $errors = app(FestEligibilityRuleEngine::class)->validateStudent($student, $event, $item);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('gender', strtolower($errors[0]));
    }

    public function test_or_across_logic_groups(): void
    {
        $tenantId = (string) Str::uuid();
        $event = FestEvent::create([
            'tenant_id' => $tenantId,
            'title' => 'Quiz',
            'event_type' => 'custom',
            'level_round' => 'sahodaya',
            'status' => 'draft',
        ]);
        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Junior',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);

        FestEligibilityRule::create([
            'tenant_id' => $tenantId,
            'scope_type' => FestEligibilityRule::SCOPE_EVENT,
            'scope_id' => $event->id,
            'rule_type' => 'gender',
            'operator' => 'in',
            'value_json' => ['in' => ['female']],
            'logic_group' => 0,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        FestEligibilityRule::create([
            'tenant_id' => $tenantId,
            'scope_type' => FestEligibilityRule::SCOPE_EVENT,
            'scope_id' => $event->id,
            'rule_type' => 'gender',
            'operator' => 'in',
            'value_json' => ['in' => ['male']],
            'logic_group' => 1,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $student = new Student(['gender' => 'male']);
        $this->assertSame([], app(FestEligibilityRuleEngine::class)->validateStudent($student, $event, $item));
    }
}
