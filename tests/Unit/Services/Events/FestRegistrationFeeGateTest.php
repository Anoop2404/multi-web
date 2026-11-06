<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Services\Events\FestRegistrationBatchFeeService;
use App\Services\Events\FestRegistrationFeeGate;
use App\Services\Events\FestSchoolEventFeeService;
use Tests\TestCase;

class FestRegistrationFeeGateTest extends TestCase
{
    public function test_fee_gate_is_opt_in_for_every_event_type(): void
    {
        foreach (['sports', 'kalolsavam'] as $eventType) {
            foreach ([null, false, true] as $setting) {
                $event = new FestEvent(['event_type' => $eventType]);
                $fees = $this->createMock(FestSchoolEventFeeService::class);
                $fees->method('feeRequired')->willReturn(true);
                $fees->method('resolveSchedule')->willReturn(
                    $setting === null ? [] : ['require_fee_before_registration' => $setting]
                );
                $gate = new FestRegistrationFeeGate($fees, $this->createMock(FestRegistrationBatchFeeService::class));

                $this->assertSame($setting === true, $gate->requiredBeforeRegistration($event));
            }
        }
    }
}
