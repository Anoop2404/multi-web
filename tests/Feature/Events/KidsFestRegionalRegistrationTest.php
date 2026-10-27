<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventClassGroup;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegionChangeRequest;
use App\Models\FestRegistration;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestRegistrationEligibilityService;
use App\Services\Events\FestSchoolEventRegionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class KidsFestRegionalRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $sahodaya = Tenant::create(['id' => 'kids-sahodaya', 'name' => 'Kids Sahodaya', 'type' => 'sahodaya']);
        $school = Tenant::create(['id' => 'kids-school', 'name' => 'Kids School', 'type' => 'school', 'parent_id' => $sahodaya->id]);
        $root = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Kids Fest', 'event_type' => 'kids_fest', 'status' => 'registration_open', 'conduct_mode' => 'partitioned', 'fee_settings' => ['class_group_scheme' => 'custom']]);
        $children = collect(['Manjeri', 'Nilambur'])->map(function ($name) use ($root, $sahodaya) {
            $region = Region::create(['tenant_id' => $sahodaya->id, 'name' => $name, 'code' => $name, 'is_active' => true]);

            return FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => $name, 'event_type' => 'kids_fest', 'status' => 'registration_open', 'parent_event_id' => $root->id, 'partition_role' => 'region', 'partition_key' => strtolower($name), 'region_id' => $region->id]);
        });

        return [$sahodaya, $school, $root, $children];
    }

    public function test_membership_region_does_not_select_or_lock_the_kids_fest_region(): void
    {
        [$sahodaya, $school, $root, $children] = $this->fixture();
        \App\Models\SchoolRegionAssignment::create([
            'tenant_id' => $sahodaya->id, 'school_id' => $school->id,
            'region_id' => $children[0]->region_id,
            'academic_year' => \App\Support\AcademicYear::forSahodaya($sahodaya->id),
        ]);
        $partitions = app(\App\Services\Events\FestSchoolPartitionService::class);
        $this->assertNull($partitions->resolvePartitionKey($root, $school->id));
        $this->assertSame([$root->id], $partitions->filterVisibleToSchool(collect([$root, ...$children]), $school->id)->pluck('id')->all());
        $hub = app(\App\Services\Events\ProgramHubDataService::class)->resolveRegionOptionsForSchool($school, 'kids_fest');
        $this->assertFalse($hub['event_region_options'][0]['locked']);
        $this->assertCount(2, $hub['event_region_options'][0]['regions']);

        // A school's first event choice may differ from its membership region.
        app(FestSchoolEventRegionService::class)->select($root, $school->id, $children[1]->region_id);
        $this->assertSame('nilambur', $partitions->resolvePartitionKey($root, $school->id));
        app(\App\Services\Events\FestRegionPartitionService::class)->assertRegionSelected($children[1], $school);
        $hub = app(\App\Services\Events\ProgramHubDataService::class)->resolveRegionOptionsForSchool($school, 'kids_fest');
        $this->assertTrue($hub['event_region_options'][0]['locked']);
        $this->assertSame('Nilambur', $hub['event_region_options'][0]['current_region']);
        $this->assertDatabaseHas('school_region_assignments', ['school_id' => $school->id, 'region_id' => $children[0]->region_id]);
    }

    public function test_lkg_students_match_root_categories_and_combined_categories_on_regional_child(): void
    {
        [$sahodaya, $school, $root, $children] = $this->fixture();
        foreach (['cat1' => ['LKG'], 'cat2' => ['UKG'], 'kg' => ['LKG', 'UKG']] as $key => $classes) {
            FestEventClassGroup::create(['tenant_id' => $sahodaya->id, 'event_id' => $root->id, 'key' => $key, 'label' => $key, 'classes' => $classes]);
        }
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'LKG']);
        $student = Student::create(['tenant_id' => $school->id, 'name' => 'LKG Student', 'school_class_id' => $class->id, 'gender' => 'male']);
        $student->load('schoolClass');
        $service = app(FestRegistrationEligibilityService::class);
        $row = $service->annotateStudents(collect([$student]), $children[0])->first();
        $this->assertEqualsCanonicalizing(['cat1', 'kg'], $row['eligible_class_groups']);
        $this->assertTrue($row['eligible_kids_fest']);
        foreach (['cat1', 'kg'] as $group) {
            $item = new FestEventItem(['class_group' => $group, 'gender' => 'open']);
            $errors = $service->validateStudent($student, $children[0], $item);
            $this->assertFalse(collect($errors)->contains(fn ($error) => str_contains($error, 'category configured')));
        }
        $errors = $service->validateStudent($student, $children[0], new FestEventItem(['class_group' => 'cat2', 'gender' => 'open']));
        $this->assertTrue(collect($errors)->contains(fn ($error) => str_contains($error, 'category configured')));
    }

    public function test_school_and_admin_can_request_and_review_an_event_region_change(): void
    {
        [$sahodaya, $school, $root, $children] = $this->fixture();
        $otherFest = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Other Kids Fest', 'event_type' => 'kids_fest', 'status' => 'registration_open']);
        $foreignRegion = Region::create(['tenant_id' => $sahodaya->id, 'fest_event_id' => $otherFest->id, 'name' => 'Other fest venue', 'code' => 'OTHER', 'is_active' => true]);
        $unconfiguredRegion = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Membership only', 'code' => 'MEM', 'is_active' => true]);
        // Even a mistakenly linked child must not expose another fest's region.
        FestEvent::create(['tenant_id' => $sahodaya->id, 'parent_event_id' => $root->id, 'title' => 'Wrong fest region', 'event_type' => 'kids_fest', 'partition_role' => 'region', 'partition_key' => 'wrong', 'region_id' => $foreignRegion->id, 'status' => 'registration_open']);
        foreach ($children as $child) {
            $child->region->update(['fest_event_id' => $root->id]);
        }
        $this->seed(RolesAndPermissionsSeeder::class);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'KID', 'student_data_mode' => 'counts_only']);
        $school->update(['is_active' => true, 'membership_status' => 'approved']);
        $schoolUser = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolUser->assignRole('school_admin');
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');
        $base = '/school-admin/'.$school->id.'/kids-fest/events/'.$root->id;
        $this->actingAs($schoolUser)->post($base.'/phase-region', ['region_id' => $children[0]->region_id])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/school-admin/'.$school->id.'/kids-fest/events/'.$children[0]->id.'/registration')->assertOk()->assertInertia(fn ($page) => $page->component('School/Events/Registration', false)->where('schoolRegion.locked', true)->has('schoolRegion.regions', 2));
        $this->get($base.'/region-change-requests')->assertOk()->assertInertia(fn ($page) => $page->component('School/Events/RegionChangeRequests', false)->where('phases.0.locked', true)->has('phases.0.regions', 2));
        $this->post($base.'/phase-region', ['region_id' => $children[1]->region_id])->assertSessionHasErrors('region_id');
        $this->get($base.'/region-change-requests')->assertOk()->assertInertia(fn ($page) => $page
            ->where('eventScoped', true)->where('event.id', $root->id)
            ->where('phases.0.regions.0.region_id', $children[0]->region_id)
            ->where('phases.0.regions.1.region_id', $children[1]->region_id)
            ->has('phases.0.regions', 2));
        foreach ([$foreignRegion->id, $unconfiguredRegion->id, $children[0]->region_id] as $invalidRegion) {
            $this->post($base.'/region-change-requests', ['requested_region_id' => $invalidRegion, 'reason' => 'Invalid choice'])
                ->assertSessionHasErrors('requested_region_id');
        }
        $this->post($base.'/region-change-requests', ['requested_region_id' => $children[1]->region_id, 'reason' => 'Closer venue'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($base.'/region-change-requests', ['requested_region_id' => $children[1]->region_id, 'reason' => 'Duplicate'])
            ->assertSessionHasErrors('requested_region_id');
        $this->assertSame(1, FestRegionChangeRequest::where('event_id', $root->id)->where('school_id', $school->id)->count());
        $change = FestRegionChangeRequest::where('event_id', $root->id)->firstOrFail();
        $adminBase = '/sahodaya-admin/'.$sahodaya->id.'/events/'.$root->id;
        $this->actingAs($admin)->get($adminBase.'/phases/regions-matrix')->assertOk()->assertInertia(fn ($page) => $page->component('Sahodaya/Events/PhaseRegionMatrix', false)->has('pendingRequests', 1)->where('eventScoped', true));
        $this->post($adminBase.'/region-change-requests/'.$change->id.'/approve')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $change->fresh()->status);
        $this->assertSame($children[1]->id, app(FestSchoolEventRegionService::class)->current($root, $school->id)->id);
    }

    public function test_approved_event_region_move_preserves_the_registration_and_participants(): void
    {
        [, $school, $root, $children] = $this->fixture();
        $source = FestEventItem::create(['event_id' => $root->id, 'title' => 'Song', 'item_code' => 'SONG']);
        $oldItem = FestEventItem::create(['event_id' => $children[0]->id, 'title' => 'Song', 'item_code' => 'SONG', 'inherited_from_item_id' => $source->id]);
        $newItem = FestEventItem::create(['event_id' => $children[1]->id, 'title' => 'Song', 'item_code' => 'SONG', 'inherited_from_item_id' => $source->id]);
        $registration = FestRegistration::create(['event_id' => $children[0]->id, 'item_id' => $oldItem->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'event_id' => $children[0]->id, 'display_name' => 'Student']);
        $service = app(FestSchoolEventRegionService::class);
        $service->select($root, $school->id, $children[0]->region_id);
        $service->select($root, $school->id, $children[1]->region_id, override: true);
        $this->assertSame($children[1]->id, $registration->fresh()->event_id);
        $this->assertSame($newItem->id, $registration->fresh()->item_id);
        $this->assertSame($children[1]->id, $participant->fresh()->event_id);
        FestMark::create(['event_id' => $children[1]->id, 'item_id' => $newItem->id, 'participant_id' => $participant->id, 'score' => 90]);
        try {
            $service->select($root, $school->id, $children[0]->region_id, override: true);
            $this->fail('Region changes after marks must be blocked.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame($children[1]->id, $service->current($root, $school->id)->id);
    }

    public function test_event_region_is_locked_after_selection_and_does_not_change_other_events(): void
    {
        [, $school, $root, $children] = $this->fixture();
        $service = app(FestSchoolEventRegionService::class);
        $service->select($root, $school->id, $children[0]->region_id);
        $this->assertSame($children[0]->id, $service->current($root, $school->id)->id);
        try {
            $service->select($root, $school->id, $children[1]->region_id);
            $this->fail('A school must request approval to change region.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('region_id', $exception->errors());
        }
        $this->assertSame($children[0]->id, $service->current($root, $school->id)->id);
        $this->assertDatabaseCount('school_region_assignments', 0);
        $request = FestRegionChangeRequest::create(['event_id' => $root->id, 'phase_id' => null, 'school_id' => $school->id, 'current_region_id' => $children[0]->region_id, 'requested_region_id' => $children[1]->region_id, 'reason' => 'Closer venue', 'status' => 'pending']);
        $this->assertNull($request->fresh()->phase_id);
        $service->select($root, $school->id, $children[1]->region_id, override: true);
        $this->assertSame($children[1]->id, $service->current($root, $school->id)->id);
    }
}
