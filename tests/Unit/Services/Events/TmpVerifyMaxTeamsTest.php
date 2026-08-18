<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestRegistrationCreateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TmpVerifyMaxTeamsTest extends TestCase
{
    use RefreshDatabase;

    private function makeFixtures(?int $maxPerSchool): array
    {
        $sahodaya = Tenant::create([
            'id' => 'tmp-sah-01',
            'name' => 'Tmp Sahodaya',
            'type' => 'sahodaya',
            'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Tmp Kalotsav',
            'event_type' => 'kalotsav',
            'status' => 'registration_open',
            'approval_policy' => 'auto',
        ]);

        $head = FestItemHead::create([
            'tenant_id' => $sahodaya->id,
            'event_id' => $event->id,
            'event_type' => 'kalotsav',
            'name' => 'Group Dance Head',
            'slug' => 'group-dance-head',
            'max_teams' => 2,
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'head_id' => $head->id,
            'title' => 'Group Dance',
            'participant_type' => 'team',
            'is_enabled' => true,
            'max_per_school' => $maxPerSchool,
        ]);

        $school = Tenant::create([
            'id' => 'tmp-sch-01',
            'name' => 'Tmp School',
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $school->id,
            'name' => 'Class 5',
        ]);

        $students = [];
        for ($i = 1; $i <= 6; $i++) {
            $students[] = Student::create([
                'tenant_id' => $school->id,
                'school_class_id' => $class->id,
                'name' => "Student $i",
                'admission_number' => "adm-$i",
                'status' => 'active',
                'verified_at' => now(),
            ]);
        }

        return [$event, $item, $school, $students];
    }

    public function test_scenario_a_default_max_per_school_blocks_second_team_at_app_layer_no_db_involved(): void
    {
        [$event, $item, $school, $students] = $this->makeFixtures(null); // max_per_school left null -> defaults to 1

        $service = app(FestRegistrationCreateService::class);

        $reg1 = $service->createForSchool($event, $item, $school, [$students[0]->id, $students[1]->id], [], 'Team A');
        $this->assertNotNull($reg1->id);

        try {
            $service->createForSchool($event, $item, $school, [$students[2]->id, $students[3]->id], [], 'Team B');
            $this->fail('Expected ValidationException for second team when max_per_school defaults to 1, even though max_teams=2.');
        } catch (ValidationException $e) {
            $bag = $e->errors();
            fwrite(STDERR, "\n[Scenario A] ValidationException fields: ".json_encode($bag)."\n");
            $this->assertTrue(
                str_contains(json_encode($bag), 'already has an entry'),
                'Expected the "already has an entry" message.'
            );
        }

        $count = DB::table('fest_registrations')->where('event_id', $event->id)->where('school_id', $school->id)->where('item_id', $item->id)->count();
        fwrite(STDERR, "[Scenario A] final registration count for (event,school,item): $count\n");
        $this->assertEquals(1, $count);
    }

    public function test_scenario_b_max_per_school_2_allows_two_teams_on_plain_sqlite_no_extra_index(): void
    {
        [$event, $item, $school, $students] = $this->makeFixtures(2); // max_per_school = 2, matches max_teams = 2

        // Confirm the pgsql-only partial unique index is NOT present on this sqlite connection.
        $driver = Schema::getConnection()->getDriverName();
        fwrite(STDERR, "\n[Scenario B] DB driver under test: $driver\n");
        $this->assertEquals('sqlite', $driver);
        $hasIndex = DB::select("SELECT 1 FROM sqlite_master WHERE type='index' AND name='fest_reg_active_unique'");
        fwrite(STDERR, '[Scenario B] fest_reg_active_unique present on sqlite by default: '.(count($hasIndex) ? 'YES' : 'NO')."\n");
        $this->assertCount(0, $hasIndex);

        $service = app(FestRegistrationCreateService::class);

        $reg1 = $service->createForSchool($event, $item, $school, [$students[0]->id, $students[1]->id], [], 'Team A');
        $this->assertNotNull($reg1->id);

        $reg2 = $service->createForSchool($event, $item, $school, [$students[2]->id, $students[3]->id], [], 'Team B');
        $this->assertNotNull($reg2->id);
        $this->assertNotEquals($reg1->id, $reg2->id);

        $count = DB::table('fest_registrations')->where('event_id', $event->id)->where('school_id', $school->id)->where('item_id', $item->id)->count();
        fwrite(STDERR, "[Scenario B] final registration count for (event,school,item): $count (both teams succeeded at the app layer)\n");
        $this->assertEquals(2, $count);
    }

    public function test_scenario_c_with_manually_added_pgsql_style_partial_index_second_legit_team_fails(): void
    {
        [$event, $item, $school, $students] = $this->makeFixtures(2); // max_per_school = 2, matches max_teams = 2

        // Manually recreate the exact production (pgsql-only) partial unique index on this
        // sqlite connection, to see what would happen if the same constraint existed here.
        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX fest_reg_active_unique
    ON fest_registrations (event_id, school_id, item_id)
    WHERE status NOT IN ('withdrawn', 'rejected')
SQL);

        $service = app(FestRegistrationCreateService::class);

        $reg1 = $service->createForSchool($event, $item, $school, [$students[0]->id, $students[1]->id], [], 'Team A');
        $this->assertNotNull($reg1->id);

        try {
            $service->createForSchool($event, $item, $school, [$students[2]->id, $students[3]->id], [], 'Team B');
            $this->fail('Expected the DB-level unique index to block the second, otherwise-legitimate team.');
        } catch (ValidationException $e) {
            $bag = $e->errors();
            fwrite(STDERR, "\n[Scenario C] Caught ValidationException (friendly path fired), fields: ".json_encode($bag)."\n");
            $this->assertTrue(str_contains(json_encode($bag), 'already has an entry'));
        } catch (\Illuminate\Database\QueryException $e) {
            fwrite(STDERR, "\n[Scenario C] Caught raw QueryException (friendly-conversion did NOT fire on sqlite): ".$e->getMessage()."\n");
            fwrite(STDERR, '[Scenario C] errorInfo[0]='.json_encode($e->errorInfo[0] ?? null)."\n");
            // Row was still not created either way -- that is the load-bearing invariant.
        }

        $count = DB::table('fest_registrations')->where('event_id', $event->id)->where('school_id', $school->id)->where('item_id', $item->id)->count();
        fwrite(STDERR, "[Scenario C] final registration count for (event,school,item): $count (should be 1 -- team B lost despite being within max_teams quota)\n");
        $this->assertEquals(1, $count);
    }
}
