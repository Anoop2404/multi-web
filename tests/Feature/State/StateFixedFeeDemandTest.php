<?php

namespace Tests\Feature\State;

use App\Models\ExternalSahodaya;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Models\StateRemittance;
use App\Models\Tenant;
use App\Services\State\StateRemittanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The State charges a Sahodaya a FIXED fee, not a per-participant one: a Sahodaya sending 40
 * qualifiers owes exactly what one sending 4 owes.
 */
class StateFixedFeeDemandTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Idukki Sahodaya', 'is_active' => true,
        ]);
    }

    private function program(array $stateFees): FestStateProgram
    {
        return FestStateProgram::create([
            'title'          => 'Kerala State Kalotsavam 2026',
            'event_type'     => 'kalolsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'published',
            'academic_year'  => '2026-2027',
            'level_fees'     => ['state' => $stateFees],
        ]);
    }

    private function approveEntries(FestStateProgram $program, int $count): void
    {
        $intake = StateQualifierIntake::create([
            'state_program_id' => $program->id, 'source_tenant_id' => $this->sahodaya->id,
            'source_event_id' => 1, 'idempotency_key' => 'k:'.Str::random(6),
            'status' => 'received', 'payload' => [],
        ]);

        $item = FestStateProgramItem::create([
            'state_program_id' => $program->id, 'title' => 'Light Music', 'item_code' => 'LM01',
            'fee_amount' => 250,
        ]);

        for ($i = 0; $i < $count; $i++) {
            StateQualifierEntry::create([
                'intake_id' => $intake->id, 'school_id' => 'sch-'.$i, 'item_id' => $item->id,
                'item_code' => 'LM01', 'student_name' => 'Student '.$i, 'status' => 'approved',
            ]);
        }
    }

    public function test_the_demand_is_the_flat_fee_regardless_of_how_many_qualifiers_were_approved(): void
    {
        $program = $this->program(['sahodaya_registration_fee' => 1000]);
        $this->approveEntries($program, 12);

        $remittance = app(StateRemittanceService::class)->calculateDemandFor($program, $this->sahodaya);

        $this->assertSame('1000.00', (string) $remittance->amount);
        $this->assertSame(1, $remittance->lines()->count(), 'A fixed fee is one line, not one per item.');
        $this->assertSame('flat_school', $remittance->source_breakdown['fee_model']);
        // The count is recorded for audit, but it does not drive the amount.
        $this->assertSame(12, $remittance->source_breakdown['approved_nominees']);
    }

    public function test_two_sahodayas_of_very_different_size_owe_the_same(): void
    {
        $program = $this->program(['sahodaya_registration_fee' => 1000]);
        $big = $this->sahodaya;
        $small = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Small Sahodaya', 'is_active' => true,
        ]);

        $this->approveEntries($program, 40);
        $service = app(StateRemittanceService::class);

        $this->assertSame(
            (string) $service->calculateDemandFor($program, $big)->amount,
            (string) $service->calculateDemandFor($program, $small)->amount,
        );
    }

    public function test_an_unset_fee_model_bills_the_fixed_fee_rather_than_falling_through_to_per_item(): void
    {
        // A blank setting must not silently produce a per-item bill — that is the behaviour this
        // changed away from, and the seeded program had per-item item fees sitting there ready.
        $program = $this->program(['sahodaya_registration_fee' => 1000]);
        $this->approveEntries($program, 4);

        $remittance = app(StateRemittanceService::class)->calculateDemandFor($program, $this->sahodaya);

        $this->assertSame('1000.00', (string) $remittance->amount);
    }

    public function test_a_program_can_still_opt_into_per_item_billing(): void
    {
        $program = $this->program(['fee_model' => 'per_item', 'sahodaya_registration_fee' => 1000]);
        $this->approveEntries($program, 4);

        $remittance = app(StateRemittanceService::class)->calculateDemandFor($program, $this->sahodaya);

        // 1000 base + 4 x 250 item fee
        $this->assertSame('2000.00', (string) $remittance->amount);
    }

    public function test_an_outside_sahodaya_is_billed_the_same_fixed_fee(): void
    {
        // An outside Sahodaya is not a Tenant — its intakes and remittances are keyed
        // "external:{uuid}". It still takes part in the State event, so it still owes the fee;
        // approval used to raise a demand only when the source resolved to a Tenant, which billed
        // every outside Sahodaya nothing at all.
        $program = $this->program(['sahodaya_registration_fee' => 1000]);
        $outside = ExternalSahodaya::create([
            'state_program_id' => $program->id, 'name' => 'Kasaragod Sahodaya',
            'district' => 'KASARAGOD', 'access_code' => 'KSGD'.Str::upper(Str::random(4)), 'status' => 'active',
        ]);

        $remittance = app(StateRemittanceService::class)
            ->calculateFixedDemandForSource($program, 'external:'.$outside->id);

        $this->assertSame('1000.00', (string) $remittance->amount);
        $this->assertSame('external:'.$outside->id, $remittance->sahodaya_id);
        $this->assertSame(1, $remittance->lines()->count());
    }

    public function test_a_submitted_remittance_is_never_silently_recalculated(): void
    {
        $program = $this->program(['sahodaya_registration_fee' => 1000]);
        $this->approveEntries($program, 2);
        $service = app(StateRemittanceService::class);

        $remittance = $service->calculateDemandFor($program, $this->sahodaya);
        $remittance->forceFill(['status' => 'submitted', 'amount' => 1000])->save();

        $program->forceFill(['level_fees' => ['state' => ['sahodaya_registration_fee' => 5000]]])->save();
        $again = $service->calculateDemandFor($program->fresh(), $this->sahodaya);

        $this->assertSame('1000.00', (string) $again->amount, 'A paid amount must not be rewritten under the Sahodaya.');
        $this->assertSame(1, StateRemittance::count());
    }
}
