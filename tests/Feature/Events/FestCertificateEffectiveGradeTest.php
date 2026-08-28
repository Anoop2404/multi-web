<?php

namespace Tests\Feature\Events;

use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Events\FestCertificateService;
use App\Services\Events\FestGradePointService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression guard for a real, confirmed production bug: a participant's certificate
 * showed no grade for "Picture Talk", while every results screen for the exact same
 * mark correctly showed "Grade A". Traced (via a production tinker session) to the
 * actual row: score=168, position=4, grade='' — genuinely empty in fest_marks.grade.
 * FestItemResultsService::resultRowsForItem() never reads that raw column directly; it
 * derives an "effective grade" live from score via FestGradePointService::
 * resolveGradeFromScore() first, falling back to the raw column only if that comes back
 * empty — which is exactly why every results view showed "A" while the certificate,
 * which read $mark->grade directly, showed nothing. FestCertificateService now computes
 * the grade the same way (effectiveGrade()) for both winner and participation
 * certificates.
 */
class FestCertificateEffectiveGradeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, event: FestEvent} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Effective Grade Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'EG', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Effective Grade School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        // No FestGradeConfig rows and no scoring_preset — resolveGradeFromScore() falls
        // through to the "standard Kalotsavam percentage" scale, so a score's derived
        // grade is fully predictable from percent-of-total_marks alone.
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Effective Grade Event', 'event_type' => 'kalolsavam']);

        return compact('sahodaya', 'school', 'event');
    }

    public function test_winner_certificate_shows_a_score_derived_grade_when_the_raw_grade_column_is_empty(): void
    {
        $f = $this->fixture();
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Picture Talk', 'item_code' => 'EG1', 'total_marks' => 200]);

        $registration = FestRegistration::create([
            'event_id' => $f['event']->id, 'item_id' => $item->id, 'school_id' => $f['school']->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $f['event']->id, 'student_id' => 951,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        // Same score/grade shape as FAIZAN's real row (score set, grade left empty), but
        // position 1 — a cert_type='winner' Certificate only ever exists for a top-3
        // finish in the first place (see generateForEvent()'s own position<=3 gate), so
        // this is what actually exercises the "secured Nth Prize with X Grade" phrasing.
        FestMark::create(['event_id' => $f['event']->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'score' => 168, 'grade' => '']);

        $certificate = Certificate::create([
            'entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
            'cert_type' => 'winner', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(),
        ]);

        $expectedGrade = app(FestGradePointService::class)->resolveGradeFromScore($f['event'], $item->id, 168.0);
        $this->assertNotEmpty($expectedGrade, 'Test setup sanity check: the score must actually derive a real grade.');

        $context = app(FestCertificateService::class)->renderContext($certificate);

        $this->assertSame($expectedGrade, $context['fieldValues']['grade']);
        $this->assertStringContainsString("secured First Prize with {$expectedGrade} Grade", $context['fieldValues']['achievement_line']);
    }

    public function test_participation_certificate_shows_a_score_derived_grade_for_an_item_with_no_raw_grade(): void
    {
        $f = $this->fixture();
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Picture Talk', 'item_code' => 'EG2', 'total_marks' => 200]);

        $registration = FestRegistration::create([
            'event_id' => $f['event']->id, 'item_id' => $item->id, 'school_id' => $f['school']->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $f['event']->id, 'student_id' => 952,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        FestMark::create(['event_id' => $f['event']->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 4, 'score' => 168, 'grade' => '']);

        $certificate = Certificate::create([
            'entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
            'cert_type' => 'participation', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(),
        ]);

        $expectedGrade = app(FestGradePointService::class)->resolveGradeFromScore($f['event'], $item->id, 168.0);
        $this->assertNotEmpty($expectedGrade);

        $context = app(FestCertificateService::class)->renderContext($certificate);

        $this->assertStringContainsString("— Grade {$expectedGrade}", $context['fieldValues']['participation_items_box']);
        $this->assertSame($expectedGrade, $context['fieldValues']['grade']);
    }

    public function test_raw_grade_column_still_wins_when_score_derivation_comes_back_empty(): void
    {
        $f = $this->fixture();
        // No total_marks configured, and a negative/zero score can't derive a percentage
        // — resolveGradeFromScore() should return null here, so the explicitly-set raw
        // grade must still come through unchanged.
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'EG3']);

        $registration = FestRegistration::create([
            'event_id' => $f['event']->id, 'item_id' => $item->id, 'school_id' => $f['school']->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $f['event']->id, 'student_id' => 953,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        FestMark::create(['event_id' => $f['event']->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'score' => 0, 'grade' => 'A+']);

        $certificate = Certificate::create([
            'entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
            'cert_type' => 'winner', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(),
        ]);

        $context = app(FestCertificateService::class)->renderContext($certificate);

        $this->assertSame('A+', $context['fieldValues']['grade']);
    }
}
