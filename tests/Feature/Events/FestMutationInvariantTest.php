<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestJudgeScoreService;
use App\Services\Events\FestMarkSaveService;
use App\Services\Events\FestRegistrationImportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FestMutationInvariantTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Mutation Scope Sahodaya',
            'domain' => 'mutation-scope.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'MS',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Mutation Scope School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Mutation Scope Event',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
        ]);
        $registeredItem = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Registered Item',
            'category' => 'music',
            'item_code' => 'REG',
        ]);
        $otherItem = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Other Item',
            'category' => 'music',
            'item_code' => 'OTH',
        ]);
        $registration = FestRegistration::create([
            'event_id' => $event->id,
            'item_id' => $registeredItem->id,
            'school_id' => $school->id,
            'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id,
            'participant_type' => 'student',
            'participant_role' => 'performer',
        ]);

        return compact('sahodaya', 'school', 'admin', 'event', 'registeredItem', 'otherItem', 'participant');
    }

    public function test_mark_service_rejects_an_item_the_participant_did_not_register_for(): void
    {
        ['event' => $event, 'otherItem' => $otherItem, 'participant' => $participant] = $this->fixture();

        try {
            app(FestMarkSaveService::class)->save($event, [
                'participant_id' => $participant->id,
                'item_id' => $otherItem->id,
                'score' => 90,
            ], 1);
            $this->fail('Expected mismatched participant/item validation to fail.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame(0, FestMark::count());
    }

    public function test_judge_score_service_rejects_an_item_the_participant_did_not_register_for(): void
    {
        ['event' => $event, 'otherItem' => $otherItem, 'participant' => $participant] = $this->fixture();

        try {
            app(FestJudgeScoreService::class)->save($event, [
                'participant_id' => $participant->id,
                'item_id' => $otherItem->id,
                'score' => 90,
            ], 1);
            $this->fail('Expected mismatched participant/item validation to fail.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('fest_judge_scores', 0);
    }

    public function test_schedule_endpoint_rejects_an_item_the_participant_did_not_register_for(): void
    {
        [
            'sahodaya' => $sahodaya,
            'admin' => $admin,
            'event' => $event,
            'otherItem' => $otherItem,
            'participant' => $participant,
        ] = $this->fixture();

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.store', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]), [
            'item_id' => $otherItem->id,
            'participant_id' => $participant->id,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, FestSchedule::count());
    }

    public function test_item_window_endpoint_cannot_publish_results(): void
    {
        [
            'sahodaya' => $sahodaya,
            'admin' => $admin,
            'event' => $event,
            'otherItem' => $item,
        ] = $this->fixture();

        $this->actingAs($admin)->patch(route('sahodaya.events.items.windows.update', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
            'item' => $item->id,
        ]), [
            'results_published_at' => now()->toDateTimeString(),
        ])->assertSessionDoesntHaveErrors();

        $this->assertNull($item->fresh()->results_published_at);
    }

    public function test_settings_publish_shortcut_enforces_mark_completeness(): void
    {
        [
            'sahodaya' => $sahodaya,
            'admin' => $admin,
            'event' => $event,
            'otherItem' => $item,
        ] = $this->fixture();

        $response = $this->actingAs($admin)->post(route('sahodaya.events.items.publish-results', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
            'item' => $item->id,
        ]));

        $response->assertStatus(422);
        $this->assertNull($item->fresh()->results_published_at);
    }

    public function test_registration_import_uses_the_canonical_approval_flow(): void
    {
        [
            'school' => $school,
            'event' => $event,
            'registeredItem' => $item,
        ] = $this->fixture();
        $event->update(['status' => 'published']);
        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class 8']);
        Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $schoolClass->id,
            'name' => 'Imported Student',
            'reg_no' => 'IMP-001',
            'status' => 'active',
            'verified_at' => now(),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'fest-registration-test-');
        $handle = fopen($path, 'w');
        fputcsv($handle, ['item_id', 'reg_no', 'team_name', 'role']);
        fputcsv($handle, [$item->id, 'IMP-001', '', 'performer']);
        fclose($handle);

        try {
            $result = app(FestRegistrationImportService::class)
                ->importFromCsv($event->fresh(), $school, $path);
        } finally {
            @unlink($path);
        }

        $this->assertSame(1, $result['imported'], json_encode($result));
        $this->assertSame(0, $result['skipped']);
        $this->assertSame('approved', FestRegistration::latest('id')->first()->status);
    }
}
