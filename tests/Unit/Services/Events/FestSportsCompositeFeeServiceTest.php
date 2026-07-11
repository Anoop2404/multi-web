<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGroup;
use App\Models\FestItemHead;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestParticipationLimitService;
use App\Services\Events\FestRegistrationService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Events\FestSportsCompositeFeeService;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FestSportsCompositeFeeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('fest_item_heads', 'school_registration_fee')
            || ! Schema::hasColumn('fest_school_event_fees', 'head_id')) {
            $this->markTestSkipped('Composite per-head fee schema is not migrated in this environment.');
        }
    }

    /** @return array{sahodaya: Tenant, school: Tenant, event: FestEvent, head: FestItemHead} */
    private function sportsContext(array $headFees = []): array
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Composite Fee Sahodaya',
            'domain' => 'composite-fee.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'CFS',
            'student_data_mode' => 'full_records',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Composite Fee School',
            'parent_id' => $sahodaya->id,
            'school_prefix' => 'CF',
            'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Sports Meet Composite',
            'event_type' => 'sports',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
            'fee_settings' => [
                'fee_model' => 'sports_composite',
            ],
        ]);

        $head = FestItemHead::create(array_merge([
            'tenant_id' => $sahodaya->id,
            'event_id' => $event->id,
            'name' => 'Athletics',
            'slug' => 'athletics',
            'sort_order' => 1,
            'school_registration_fee' => 100,
            'student_registration_fee' => 100,
            'team_registration_fee' => 200,
            'included_items_per_student' => 0,
            'included_teams' => 0,
            'verification_policy' => 'all_students',
            'approval_policy' => 'auto',
        ], $headFees));

        return compact('sahodaya', 'school', 'event', 'head');
    }

    private function makeStudent(Tenant $school, string $name = 'Athlete'): Student
    {
        $schoolClass = SchoolClass::where('tenant_id', $school->id)->first()
            ?? SchoolClass::create([
                'tenant_id' => $school->id,
                'name' => '10',
                'class_category_id' => 1,
                'is_active' => true,
            ]);

        return Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $schoolClass->id,
            'name' => $name,
            'gender' => 'male',
            'dob' => '2012-01-01',
            'status' => 'active',
        ]);
    }

    private function makeItem(FestEvent $event, FestItemHead $head, array $attrs = []): FestEventItem
    {
        return FestEventItem::create(array_merge([
            'event_id' => $event->id,
            'head_id' => $head->id,
            'title' => '100m',
            'participant_type' => 'individual',
            'class_group' => 'hs',
            'is_enabled' => true,
            'quota_eligible' => false,
        ], $attrs));
    }

    private function registerStudent(
        FestEvent $event,
        FestEventItem $item,
        Tenant $school,
        Student $student,
        string $status = 'approved',
    ): FestRegistration {
        $registration = FestRegistration::create([
            'event_id' => $event->id,
            'item_id' => $item->id,
            'school_id' => $school->id,
            'status' => $status,
        ]);

        FestParticipant::create([
            'registration_id' => $registration->id,
            'student_id' => $student->id,
            'participant_role' => 'performer',
        ]);

        return $registration;
    }

    public function test_calculate_for_head_basic_school_student_and_item_fees(): void
    {
        ['school' => $school, 'event' => $event, 'head' => $head] = $this->sportsContext();
        $student = $this->makeStudent($school);
        $item = $this->makeItem($event, $head, ['fee_amount' => 150, 'title' => 'Long jump']);
        $this->registerStudent($event, $item, $school, $student);

        $result = app(FestSportsCompositeFeeService::class)->calculateForHead($head->fresh(), $school->id);

        // school 100 + student base 100 + item 150 = 350
        $this->assertSame(100.0, $result['school_reg']);
        $this->assertSame(100.0, $result['student_reg']);
        $this->assertSame(150.0, $result['item_fee']);
        $this->assertSame(0.0, $result['team_fee']);
    }

    public function test_quota_waives_first_eligible_item_only(): void
    {
        ['school' => $school, 'event' => $event, 'head' => $head] = $this->sportsContext([
            'included_items_per_student' => 1,
            'student_registration_fee' => 100,
        ]);
        $student = $this->makeStudent($school);
        $first = $this->makeItem($event, $head, [
            'title' => 'Item A',
            'fee_amount' => 100,
            'quota_eligible' => true,
        ]);
        $second = $this->makeItem($event, $head, [
            'title' => 'Item B',
            'fee_amount' => 100,
            'quota_eligible' => true,
        ]);

        $this->registerStudent($event, $first, $school, $student);
        $this->registerStudent($event, $second, $school, $student);

        $result = app(FestSportsCompositeFeeService::class)->calculateForHead($head->fresh(), $school->id);

        $this->assertSame(100.0, $result['school_reg']);
        $this->assertSame(100.0, $result['student_reg']);
        $this->assertSame(100.0, $result['item_fee']); // one waived, one billed
        $waived = collect($result['lines'])->where('line_type', 'item_fee_waived')->count();
        $billed = collect($result['lines'])->where('line_type', 'item_fee')->count();
        $this->assertSame(1, $waived);
        $this->assertSame(1, $billed);
    }

    public function test_quota_exhaustion_fifo_across_three_items(): void
    {
        ['school' => $school, 'event' => $event, 'head' => $head] = $this->sportsContext([
            'included_items_per_student' => 1,
        ]);
        $student = $this->makeStudent($school);

        foreach (['A', 'B', 'C'] as $title) {
            $item = $this->makeItem($event, $head, [
                'title' => $title,
                'fee_amount' => 50,
                'quota_eligible' => true,
            ]);
            $this->registerStudent($event, $item, $school, $student);
        }

        $result = app(FestSportsCompositeFeeService::class)->calculateForHead($head->fresh(), $school->id);

        $this->assertSame(100.0, $result['item_fee']); // 50 + 50, first waived
        $this->assertSame(1, collect($result['lines'])->where('line_type', 'item_fee_waived')->count());
        $this->assertSame(2, collect($result['lines'])->where('line_type', 'item_fee')->count());
    }

    public function test_team_item_billed_once_with_separate_team_quota(): void
    {
        ['school' => $school, 'event' => $event, 'head' => $head] = $this->sportsContext([
            'team_registration_fee' => 200,
            'included_teams' => 1,
        ]);
        $student = $this->makeStudent($school);
        $teamItem = $this->makeItem($event, $head, [
            'title' => 'Relay',
            'participant_type' => 'team',
            'min_group_size' => 1,
            'max_group_size' => 4,
            'quota_eligible' => true,
        ]);

        $first = $this->registerStudent($event, $teamItem, $school, $student);
        FestGroup::create(['registration_id' => $first->id, 'team_name' => 'Team 1']);

        $secondStudent = $this->makeStudent($school, 'Athlete 2');
        $second = $this->registerStudent($event, $teamItem, $school, $secondStudent);
        FestGroup::create(['registration_id' => $second->id, 'team_name' => 'Team 2']);

        $result = app(FestSportsCompositeFeeService::class)->calculateForHead($head->fresh(), $school->id);

        $this->assertSame(0.0, $result['student_reg']); // teams do not trigger student base
        $this->assertSame(200.0, $result['team_fee']); // one waived by included_teams, one billed
    }

    public function test_per_head_payment_independence(): void
    {
        ['school' => $school, 'event' => $event, 'head' => $athletics] = $this->sportsContext();
        $chess = FestItemHead::create([
            'tenant_id' => $athletics->tenant_id,
            'event_id' => $event->id,
            'name' => 'Chess',
            'slug' => 'chess',
            'sort_order' => 2,
            'school_registration_fee' => 50,
            'student_registration_fee' => 50,
            'team_registration_fee' => 0,
            'included_items_per_student' => 0,
            'included_teams' => 0,
        ]);

        $student = $this->makeStudent($school);
        $athItem = $this->makeItem($event, $athletics, ['title' => '100m', 'fee_amount' => 0]);
        $chessItem = $this->makeItem($event, $chess, ['title' => 'Chess open', 'fee_amount' => 0]);
        $athReg = $this->registerStudent($event, $athItem, $school, $student);
        $chessReg = $this->registerStudent($event, $chessItem, $school, $student);

        $feeService = app(FestSchoolEventFeeService::class);
        $athFee = $feeService->recalculateForHead($event, $school->id, $athletics);
        $chessFee = $feeService->recalculateForHead($event, $school->id, $chess);

        $athFee->update([
            'status' => 'approved',
            'amount_paid' => $athFee->total_due,
        ]);

        $this->assertTrue($feeService->isPaidForRegistration($event->fresh(), $athReg->fresh()));
        $this->assertFalse($feeService->isPaidForRegistration($event->fresh(), $chessReg->fresh()));
        $this->assertGreaterThan(0, (float) $chessFee->fresh()->total_due);
    }

    public function test_cancelling_unpaid_registration_releases_quota_slot(): void
    {
        ['school' => $school, 'event' => $event, 'head' => $head] = $this->sportsContext([
            'included_items_per_student' => 1,
            'school_registration_fee' => 0,
            'student_registration_fee' => 0,
        ]);
        $student = $this->makeStudent($school);
        $first = $this->makeItem($event, $head, [
            'title' => 'First',
            'fee_amount' => 80,
            'quota_eligible' => true,
        ]);
        $second = $this->makeItem($event, $head, [
            'title' => 'Second',
            'fee_amount' => 80,
            'quota_eligible' => true,
        ]);

        $firstReg = $this->registerStudent($event, $first, $school, $student, 'submitted');
        $this->registerStudent($event, $second, $school, $student, 'submitted');

        $before = app(FestSportsCompositeFeeService::class)->calculateForHead($head->fresh(), $school->id);
        $this->assertSame(80.0, $before['item_fee']);

        $firstReg->update(['status' => 'withdrawn']);

        $after = app(FestSportsCompositeFeeService::class)->calculateForHead($head->fresh(), $school->id);
        $this->assertSame(0.0, $after['item_fee']); // second now consumes the freed quota
        $this->assertSame(1, collect($after['lines'])->where('line_type', 'item_fee_waived')->count());
    }

    public function test_cancel_blocked_when_head_payment_approved(): void
    {
        ['school' => $school, 'event' => $event, 'head' => $head] = $this->sportsContext([
            'school_registration_fee' => 100,
            'student_registration_fee' => 0,
        ]);
        $student = $this->makeStudent($school);
        $item = $this->makeItem($event, $head, ['fee_amount' => 0]);
        $registration = $this->registerStudent($event, $item, $school, $student);

        $feeService = app(FestSchoolEventFeeService::class);
        $fee = $feeService->recalculateForHead($event, $school->id, $head);
        $fee->update([
            'status' => 'approved',
            'amount_paid' => $fee->total_due,
        ]);

        $this->expectException(HttpException::class);
        app(FestRegistrationService::class)->cancel($registration->fresh(), $event->fresh());
    }

    public function test_max_participants_cap_blocks_when_reached(): void
    {
        ['school' => $school, 'event' => $event, 'head' => $head] = $this->sportsContext([
            'max_participants' => 1,
        ]);
        $item = $this->makeItem($event, $head);
        $studentA = $this->makeStudent($school, 'A');
        $studentB = $this->makeStudent($school, 'B');
        $this->registerStudent($event, $item, $school, $studentA);

        $errors = (new FestParticipationLimitService($event->fresh()))
            ->validateRegistration($item->fresh(), $school->id, [$studentB->id]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('participant cap', implode(' ', $errors));
    }

    public function test_max_participants_unlimited_when_null_or_zero(): void
    {
        ['school' => $school, 'event' => $event, 'head' => $head] = $this->sportsContext([
            'max_participants' => null,
        ]);
        $item = $this->makeItem($event, $head, ['max_per_school' => 0]);
        $student = $this->makeStudent($school);

        $errors = (new FestParticipationLimitService($event->fresh()))
            ->validateRegistration($item->fresh(), $school->id, [$student->id]);

        $this->assertSame([], array_values(array_filter(
            $errors,
            fn (string $e) => str_contains($e, 'participant cap') || str_contains($e, 'team cap')
        )));
    }

    public function test_max_teams_cap_blocks_team_items_separately(): void
    {
        ['school' => $school, 'event' => $event, 'head' => $head] = $this->sportsContext([
            'max_teams' => 1,
            'max_participants' => 50,
        ]);
        $teamItem = $this->makeItem($event, $head, [
            'participant_type' => 'team',
            'min_group_size' => 1,
            'max_group_size' => 4,
            'max_per_school' => 0,
        ]);
        $studentA = $this->makeStudent($school, 'A');
        $studentB = $this->makeStudent($school, 'B');
        $this->registerStudent($event, $teamItem, $school, $studentA);

        $errors = (new FestParticipationLimitService($event->fresh()))
            ->validateRegistration($teamItem->fresh(), $school->id, [$studentB->id]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('team cap', implode(' ', $errors));
    }

    public function test_uses_per_head_billing_requires_sports_composite_and_heads(): void
    {
        ['event' => $event] = $this->sportsContext();
        $feeService = app(FestSchoolEventFeeService::class);

        $this->assertTrue($feeService->usesPerHeadBilling($event->fresh()));

        $event->update(['fee_settings' => ['fee_model' => 'per_item']]);
        $this->assertFalse($feeService->usesPerHeadBilling($event->fresh()));
    }
}
