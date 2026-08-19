<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestVenue;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestIdCardService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestIdCardVenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_id_card_resolves_assigned_regional_venues(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Malappuram Central Sahodaya',
            'domain' => 'sahodaya-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'MCS',
            'student_data_mode' => 'counts_only',
        ]);

        $parentEvent = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'English Fest 2026-27',
            'event_type' => 'kalotsavam',
            'status' => 'published',
            'conduct_mode' => 'partitioned',
            'academic_year' => '2026-27',
        ]);

        $region1 = Region::create([
            'tenant_id' => $sahodaya->id,
            'name' => 'Region 1 (Tirur)',
            'code' => 'R1',
        ]);

        $region2 = Region::create([
            'tenant_id' => $sahodaya->id,
            'name' => 'Region 2 (Manjeri)',
            'code' => 'R2',
        ]);

        $childEvent1 = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'parent_event_id' => $parentEvent->id,
            'region_id' => $region1->id,
            'title' => 'English Fest 2026-27 — REGION 1 (TIRUR)',
            'event_type' => 'kalotsavam',
            'status' => 'published',
        ]);

        $childEvent2 = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'parent_event_id' => $parentEvent->id,
            'region_id' => $region2->id,
            'title' => 'English Fest 2026-27 — REGION 2 (MANJERI)',
            'event_type' => 'kalotsavam',
            'status' => 'published',
        ]);

        // Assigned regional venues stored in fest_venues table
        FestVenue::create([
            'tenant_id' => $sahodaya->id,
            'event_id' => $parentEvent->id,
            'region_id' => $region1->id,
            'name' => 'MES Central School, Tirur',
            'is_active' => true,
        ]);

        FestVenue::create([
            'tenant_id' => $sahodaya->id,
            'event_id' => $parentEvent->id,
            'region_id' => $region2->id,
            'name' => 'Najath English Medium School, Karuvarakundu',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Test School',
            'sahodaya_id' => $sahodaya->id,
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $school->id,
            'name' => 'Class 10',
        ]);

        $student = Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $class->id,
            'name' => 'John Doe',
            'gender' => 'male',
        ]);

        $item1 = FestEventItem::create([
            'event_id' => $childEvent1->id,
            'title' => 'Essay Writing',
            'is_enabled' => true,
        ]);

        $reg1 = FestRegistration::create([
            'event_id' => $childEvent1->id,
            'school_id' => $school->id,
            'item_id' => $item1->id,
            'status' => 'approved',
        ]);

        $participant1 = FestParticipant::create([
            'registration_id' => $reg1->id,
            'student_id' => $student->id,
            'participant_role' => 'performer',
        ]);

        $service = app(FestIdCardService::class);
        $cards = $service->cards($childEvent1, 'student', ['school_id' => $school->id]);

        $this->assertNotEmpty($cards);
        $this->assertSame('MES Central School, Tirur', $cards[0]['venue']);
    }
}
