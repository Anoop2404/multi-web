<?php

namespace Tests\Unit\Services\Training;

use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingResourcePerson;
use App\Models\TrainingSession;
use App\Services\Reports\ErpReportQueryService;
use App\Support\ErpReportMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingResourcePersonReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_rpt_trn_012_uses_resource_person_name_not_session_title(): void
    {
        $sahodayaId = (string) Str::uuid();

        Tenant::create([
            'id' => $sahodayaId,
            'name' => 'RP Sahodaya',
            'type' => 'sahodaya',
            'is_active' => true,
        ]);

        $person = TrainingResourcePerson::create([
            'tenant_id' => $sahodayaId,
            'name' => 'Dr. Anitha Nair',
            'designation' => 'Master Trainer',
            'is_active' => true,
        ]);

        $program = TrainingProgram::create([
            'tenant_id' => $sahodayaId,
            'title' => 'NCF Orientation',
            'status' => 'published',
            'fee_type' => 'none',
        ]);

        $program->resourcePersons()->attach($person->id, [
            'honorarium' => 5000,
            'role' => 'trainer',
        ]);

        TrainingSession::create([
            'program_id' => $program->id,
            'title' => 'Day 1 Morning',
            'resource_person_id' => $person->id,
            'scheduled_at' => '2025-08-01 09:00:00',
        ]);

        TrainingSession::create([
            'program_id' => $program->id,
            'title' => 'Day 1 Afternoon',
            'resource_person_id' => $person->id,
            'scheduled_at' => '2025-08-01 14:00:00',
        ]);

        $rows = app(ErpReportQueryService::class)->rows($sahodayaId, 'RPT-TRN-012', []);

        $this->assertCount(1, $rows);
        $this->assertSame('Dr. Anitha Nair', $rows[0]['resource_person']);
        $this->assertNotSame('Day 1 Morning', $rows[0]['resource_person']);
        $this->assertSame('NCF Orientation', $rows[0]['program']);
        $this->assertSame(2, $rows[0]['sessions']);
        $this->assertSame('trainer', $rows[0]['role']);
        $this->assertEquals(5000, (float) $rows[0]['honorarium']);
        $this->assertContains(
            'honorarium',
            collect(ErpReportMeta::meta('RPT-TRN-012')['columns'])->pluck('key')->all()
        );
    }

    public function test_resource_person_program_relation(): void
    {
        $sahodayaId = (string) Str::uuid();

        Tenant::create([
            'id' => $sahodayaId,
            'name' => 'RP Sahodaya 2',
            'type' => 'sahodaya',
            'is_active' => true,
        ]);

        $person = TrainingResourcePerson::create([
            'tenant_id' => $sahodayaId,
            'name' => 'Facilitator One',
            'is_active' => true,
        ]);

        $program = TrainingProgram::create([
            'tenant_id' => $sahodayaId,
            'title' => 'Workshop',
            'status' => 'draft',
            'fee_type' => 'none',
        ]);

        $program->resourcePersons()->attach($person->id, ['role' => 'facilitator']);

        $this->assertTrue($program->resourcePersons()->where('training_resource_persons.id', $person->id)->exists());
        $this->assertSame('facilitator', $person->fresh()->programs()->first()->pivot->role);
    }
}
