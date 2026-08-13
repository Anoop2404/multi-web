<?php

namespace Tests\Feature\Console;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestRegistration;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListPendingFestRegistrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_lists_pending_registrations_grouped_by_event_and_school(): void
    {
        $sahodaya = Tenant::create([
            'id' => 'test-sahodaya-list-pending-01',
            'name' => 'Test Sahodaya List Pending',
            'type' => 'sahodaya',
            'is_active' => true,
        ]);

        $sahodaya->run(function () use ($sahodaya) {
            $event = FestEvent::create([
                'tenant_id' => $sahodaya->id,
                'title' => 'Sample Kalotsav Event',
                'event_type' => 'kalotsav',
                'status' => 'registration_open',
            ]);

            $item = FestEventItem::create([
                'event_id' => $event->id,
                'title' => 'Folk Dance',
                'participant_type' => 'individual',
                'is_enabled' => true,
            ]);

            $school = Tenant::create([
                'id' => 'test-school-list-pending-01',
                'name' => 'St Marks High School',
                'type' => 'school',
                'parent_id' => $sahodaya->id,
                'membership_status' => 'approved',
                'is_active' => true,
            ]);

            FestRegistration::create([
                'event_id' => $event->id,
                'item_id' => $item->id,
                'school_id' => $school->id,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
        });

        $this->artisan('fest:list-pending-registrations', ['--sahodaya' => $sahodaya->id])
            ->expectsOutputToContain('Sample Kalotsav Event')
            ->assertExitCode(0);
    }
}
