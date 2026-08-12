<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestQualification;
use App\Models\FestRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestQualificationService;
use App\Services\Events\FestRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Regression test for LIFE-06 (functional audit, 2026-08-11/12): rejecting or
 * cancelling a registration that had already been promoted to the next
 * competition level (won + qualified) previously left the FestQualification
 * row and the downstream promoted registration completely untouched — the
 * participant kept showing as qualified/registered at the next level with no
 * link back to the now-invalid source. Mirrors the fixture already used by
 * FestManagedRegistrationHardeningTest::test_region_to_finale_promotion_promotes_certified_winners
 * for setting up a real region→finale promotion, then cancels the source
 * registration and asserts the qualification and the promoted registration
 * are both cleaned up.
 */
class FestQualificationRevocationOnRejectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');
    }

    /** @return array{regionEvent: FestEvent, finaleEvent: FestEvent, registration: FestRegistration, qualification: FestQualification} */
    private function promoteAWinner(): array
    {
        $sahodaya = Tenant::create(['id' => 'sahodaya-1', 'name' => 'Sahodaya One', 'type' => 'sahodaya']);
        $school = Tenant::create(['id' => 'school-1', 'name' => 'School One', 'type' => 'school', 'parent_id' => 'sahodaya-1']);

        $regionEvent = FestEvent::create([
            'tenant_id'         => 'sahodaya-1',
            'title'             => 'Region A Kalotsavam',
            'event_type'        => 'kalotsavam',
            'level_round'       => 'sahodaya',
            'results_published' => true,
            'status'            => 'ongoing',
        ]);

        $finaleEvent = FestEvent::create([
            'tenant_id'   => 'sahodaya-1',
            'title'       => 'Sahodaya Grand Finale',
            'event_type'  => 'kalotsavam',
            'level_round' => 'sahodaya',
            'status'      => 'published',
        ]);

        $regionItem = FestEventItem::create([
            'event_id'      => $regionEvent->id,
            'title'         => 'Classical Music (Solo)',
            'category'      => 'music',
            'item_code'     => 'CM01',
            'qualify_count' => 1,
        ]);

        FestEventItem::create([
            'event_id'  => $finaleEvent->id,
            'title'     => 'Classical Music (Solo)',
            'category'  => 'music',
            'item_code' => 'CM01',
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => 'school-1', 'name' => 'Class 10']);
        $student = Student::create(['name' => 'First Place Winner', 'tenant_id' => 'school-1', 'school_class_id' => $schoolClass->id]);

        $reg = FestRegistration::create([
            'event_id'  => $regionEvent->id,
            'item_id'   => $regionItem->id,
            'school_id' => $school->id,
            'status'    => 'approved',
        ]);

        $part = FestParticipant::create([
            'registration_id' => $reg->id,
            'student_id'      => $student->id,
        ]);

        FestMark::create([
            'event_id'        => $regionEvent->id,
            'item_id'         => $regionItem->id,
            'registration_id' => $reg->id,
            'participant_id'  => $part->id,
            'score'           => 98.50,
            'position'        => 1,
            'grade'           => 'A',
        ]);

        $result = app(FestQualificationService::class)->promoteWinners($regionEvent, $finaleEvent);
        $this->assertSame(1, $result['promoted']);

        $qualification = FestQualification::where('event_id', $regionEvent->id)
            ->where('item_id', $regionItem->id)
            ->firstOrFail();

        return compact('regionEvent', 'finaleEvent', 'reg', 'qualification') + ['registration' => $reg];
    }

    public function test_revoking_a_qualification_deletes_it_and_cancels_the_promoted_registration(): void
    {
        ['registration' => $registration, 'qualification' => $qualification] = $this->promoteAWinner();

        $promotedRegistrationId = FestRegistration::where('mode', 'winner_only')
            ->where('school_id', $registration->school_id)
            ->firstOrFail()
            ->id;

        app(FestQualificationService::class)->revokeQualification($qualification->fresh());

        $this->assertDatabaseMissing('fest_qualifications', ['id' => $qualification->id]);
        // revokeQualification() cancels the downstream registration (status →
        // withdrawn) rather than deleting the row — see
        // FestQualificationService::revokeQualification().
        $this->assertDatabaseHas('fest_registrations', ['id' => $promotedRegistrationId, 'status' => 'withdrawn']);
    }

    public function test_revoke_qualifications_for_registration_finds_and_revokes_all_matching_rows(): void
    {
        ['registration' => $registration] = $this->promoteAWinner();

        $revokedCount = app(FestQualificationService::class)->revokeQualificationsForRegistration($registration);

        $this->assertSame(1, $revokedCount);
        $this->assertDatabaseCount('fest_qualifications', 0);

        // A second call finds nothing left to revoke — proves the cascade
        // terminates cleanly rather than erroring on an empty set.
        $this->assertSame(0, app(FestQualificationService::class)->revokeQualificationsForRegistration($registration->fresh()));
    }
}
