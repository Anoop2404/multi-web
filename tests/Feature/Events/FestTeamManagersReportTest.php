<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\FestSchoolTeamManager;
use App\Models\Tenant;
use App\Services\Events\FestReportService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FestTeamManagersReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_can_save_contingent_team_managers_and_admin_can_export_report(): void
    {
        $sahodaya = Tenant::create([
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'subdomain' => 'test-sahodaya-' . uniqid(),
        ]);

        $schoolTenant = Tenant::create([
            'type' => 'school',
            'name' => 'St Joseph Higher Secondary School',
            'subdomain' => 'st-joseph-' . uniqid(),
            'parent_id' => $sahodaya->id,
            'school_prefix' => 'SJSS',
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Sahodaya Kalotsav 2026',
            'event_type' => 'kalotsav',
            'fee_settings' => ['fee_model' => 'none'],
        ]);

        // Register school as active participant in event
        FestRegistration::create([
            'tenant_id' => $schoolTenant->id,
            'event_id' => $event->id,
            'school_id' => $schoolTenant->id,
            'status' => 'submitted',
        ]);

        // 1. Test record creation via model / DB
        FestSchoolTeamManager::updateOrCreate(
            [
                'event_id' => $event->id,
                'school_id' => $schoolTenant->id,
            ],
            [
                'tenant_id' => $sahodaya->id,
                'manager_name_1' => 'Fr. Mathew Thomas',
                'manager_phone_1' => '9876543210',
                'manager_email_1' => 'mathew@stjoseph.edu',
                'manager_role_1' => 'Principal & Chief Contingent Leader',
                'manager_name_2' => 'Mrs. Anita Roy',
                'manager_phone_2' => '9876543211',
                'manager_email_2' => 'anita@stjoseph.edu',
                'manager_role_2' => 'Arts In-Charge Teacher',
            ]
        );

        $this->assertDatabaseHas('fest_school_team_managers', [
            'event_id' => $event->id,
            'school_id' => $schoolTenant->id,
            'manager_name_1' => 'Fr. Mathew Thomas',
            'manager_name_2' => 'Mrs. Anita Roy',
        ]);

        // 2. Test FestReportService Excel/CSV export for team managers
        $reportService = new FestReportService($event);
        $data = $reportService->teamManagersData();

        $this->assertCount(1, $data);
        $row = $data->first();
        $this->assertEquals($schoolTenant->id, $row->school_id);
        $this->assertEquals('St Joseph Higher Secondary School', $row->school_name);
        $this->assertEquals('Fr. Mathew Thomas', $row->manager_name_1);
        $this->assertEquals('9876543210', $row->manager_phone_1);
        $this->assertEquals('Mrs. Anita Roy', $row->manager_name_2);

        // 3. Test export() match calls
        $csvResponse = $reportService->export('team-managers', new Request());
        $this->assertNotNull($csvResponse);

        $pdfResponse = $reportService->export('team-managers-pdf', new Request());
        $this->assertNotNull($pdfResponse);
    }
}
