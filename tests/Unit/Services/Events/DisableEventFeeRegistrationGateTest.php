<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisableEventFeeRegistrationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_disables_existing_gates_across_sahodayas_and_preserves_fees(): void
    {
        $events = [];
        foreach (['first', 'second'] as $id) {
            $tenant = Tenant::create(['id' => $id, 'name' => $id, 'type' => 'sahodaya', 'is_active' => true]);
            foreach ([null, ['require_fee_before_registration' => true, 'fee_model' => 'flat', 'amount' => 1500]] as $settings) {
                $events[] = FestEvent::create([
                    'tenant_id' => $tenant->id, 'title' => 'Existing event',
                    'event_type' => 'sports', 'fee_settings' => $settings,
                ]);
            }
        }

        $migration = require database_path('migrations/tenant/2026_12_06_000001_disable_event_fee_registration_gate.php');
        $migration->up();
        $migration->up(); // Safe to repeat without changing other settings.

        foreach ($events as $index => $event) {
            $settings = $event->fresh()->fee_settings;
            $this->assertFalse($settings['require_fee_before_registration']);
            if ($index % 2 === 1) {
                $this->assertSame('flat', $settings['fee_model']);
                $this->assertSame(1500, $settings['amount']);
            }
        }
    }
}
