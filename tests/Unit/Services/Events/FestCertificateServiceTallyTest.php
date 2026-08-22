<?php

namespace Tests\Unit\Services\Events;

use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGroup;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Services\Events\FestCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers FestCertificateService::certificateTally() — the read-only projection behind
 * the new Sahodaya "Certificate tally" report — plus the standby-exclusion fix applied
 * to generateForEvent()/generateParticipationForEvent() alongside it.
 */
class FestCertificateServiceTallyTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(): FestEvent
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Tally Test Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);

        return FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Tally Test Fest', 'event_type' => 'kalolsavam',
        ]);
    }

    private function makeSchool(string $sahodayaId): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodayaId,
            'name' => 'Tally Test School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);
    }

    /** Auto-incremented per call so each individualParticipant() is a distinct person —
     *  needed now that participation certificates aggregate by student_id (see
     *  FestCertificateService::participationGroupsForEvent()); a shared student_id would
     *  make unrelated entries collapse into a single certificate. */
    private int $nextStudentId = 1;

    private function individualParticipant(
        FestEvent $event, FestEventItem $item, string $schoolId,
        ?int $position = null, string $role = 'performer', bool $disqualified = false,
    ): FestParticipant {
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schoolId,
            'status' => 'approved', 'submitted_at' => now(),
        ]);

        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id,
            'student_id' => $this->nextStudentId++, 'participant_type' => 'student', 'participant_role' => $role,
            'disqualified_at' => $disqualified ? now() : null,
        ]);

        if ($position !== null) {
            FestMark::create([
                'event_id' => $event->id, 'item_id' => $item->id,
                'participant_id' => $participant->id, 'grade' => 'A', 'position' => $position, 'score' => 90,
            ]);
        }

        return $participant;
    }

    public function test_individual_item_counts_top_three_as_winners_and_all_approved_as_participants(): void
    {
        $event = $this->makeEvent();
        $school = $this->makeSchool($event->tenant_id);
        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual',
            'category' => 'music', 'age_group' => 'Sub Junior', 'is_enabled' => true,
        ]);

        $this->individualParticipant($event, $item, $school->id, position: 1);
        $this->individualParticipant($event, $item, $school->id, position: 2);
        $this->individualParticipant($event, $item, $school->id, position: 3);
        $this->individualParticipant($event, $item, $school->id, position: null); // entered, didn't place
        $this->individualParticipant($event, $item, $school->id, position: null); // entered, didn't place
        $this->individualParticipant($event, $item, $school->id, role: 'standby');   // excluded entirely
        $this->individualParticipant($event, $item, $school->id, disqualified: true); // excluded entirely

        $tally = app(FestCertificateService::class)->certificateTally($event);
        $row = collect($tally['rows'])->firstWhere('item_id', $item->id);

        $this->assertNotNull($row);
        $this->assertFalse($row['is_team']);
        $this->assertSame('Sub Junior', $row['category']);
        $this->assertSame(5, $row['entry_count'], 'Standby and disqualified participants must not count as entries.');
        $this->assertSame(5, $row['participation_certs']);
        $this->assertSame(3, $row['winner_certs']);
        $this->assertSame(3, $tally['totals']['winner_certs']);
        $this->assertSame(5, $tally['totals']['participation_certs']);
        $this->assertSame(8, $tally['totals']['grand_total']);
    }

    public function test_team_item_counts_certificates_per_member_not_per_team(): void
    {
        $event = $this->makeEvent();
        $school = $this->makeSchool($event->tenant_id);
        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Group Dance', 'participant_type' => 'team',
            'category' => 'dance', 'class_group' => 'hss', 'is_enabled' => true,
        ]);
        $this->assertTrue($item->isTeamItem());

        // Winning team: 3 members, all placed 1st.
        $winningReg = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id,
            'status' => 'approved', 'submitted_at' => now(),
        ]);
        $winningGroup = FestGroup::create([
            'registration_id' => $winningReg->id, 'event_id' => $event->id, 'team_name' => 'Team A',
        ]);
        foreach (range(1, 3) as $i) {
            $member = FestParticipant::create([
                'registration_id' => $winningReg->id, 'group_id' => $winningGroup->id, 'event_id' => $event->id,
                'student_id' => $i, 'participant_type' => 'student', 'participant_role' => 'performer',
            ]);
            FestMark::create([
                'event_id' => $event->id, 'item_id' => $item->id,
                'participant_id' => $member->id, 'grade' => 'A', 'position' => 1, 'score' => 95,
            ]);
        }

        // Non-winning team: 4 members (including one standby), no mark.
        $otherReg = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id,
            'status' => 'approved', 'submitted_at' => now(),
        ]);
        $otherGroup = FestGroup::create([
            'registration_id' => $otherReg->id, 'event_id' => $event->id, 'team_name' => 'Team B',
        ]);
        foreach (range(1, 3) as $i) {
            FestParticipant::create([
                'registration_id' => $otherReg->id, 'group_id' => $otherGroup->id, 'event_id' => $event->id,
                'student_id' => 10 + $i, 'participant_type' => 'student', 'participant_role' => 'performer',
            ]);
        }
        FestParticipant::create([
            'registration_id' => $otherReg->id, 'group_id' => $otherGroup->id, 'event_id' => $event->id,
            'student_id' => 99, 'participant_type' => 'student', 'participant_role' => 'standby',
        ]);

        $tally = app(FestCertificateService::class)->certificateTally($event);
        $row = collect($tally['rows'])->firstWhere('item_id', $item->id);

        $this->assertNotNull($row);
        $this->assertTrue($row['is_team']);
        $this->assertSame(2, $row['entry_count'], 'Two teams registered.');
        $this->assertSame(6, $row['member_count'], 'Six non-standby members across both teams (standby excluded).');
        $this->assertSame(3, $row['winner_certs'], 'One winning team of 3 -> 3 individual winner certificates, not 1.');
        $this->assertSame(6, $row['participation_certs']);
    }

    public function test_generate_methods_exclude_standby_participants(): void
    {
        $event = $this->makeEvent();
        $school = $this->makeSchool($event->tenant_id);
        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual',
            'category' => 'music', 'is_enabled' => true,
        ]);

        $winner = $this->individualParticipant($event, $item, $school->id, position: 1);
        $standbyWinner = $this->individualParticipant($event, $item, $school->id, position: 2, role: 'standby');
        $standbyEntrant = $this->individualParticipant($event, $item, $school->id, role: 'standby');

        $service = app(FestCertificateService::class);

        $winnerCerts = $service->generateForEvent($event);
        $winnerEntityIds = collect($winnerCerts)->pluck('entity_id')->all();
        $this->assertContains($winner->id, $winnerEntityIds);
        $this->assertNotContains($standbyWinner->id, $winnerEntityIds, 'Standby participants must not receive winner certificates.');

        $participationCerts = $service->generateParticipationForEvent($event);
        $participationEntityIds = collect($participationCerts)->pluck('entity_id')->all();
        $this->assertContains($winner->id, $participationEntityIds);
        $this->assertNotContains($standbyEntrant->id, $participationEntityIds, 'Standby participants must not receive participation certificates.');
    }

    public function test_participation_certificates_are_aggregated_one_per_student_across_items(): void
    {
        $event = $this->makeEvent();
        $school = $this->makeSchool($event->tenant_id);

        $itemA = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Elocution', 'participant_type' => 'individual',
            'category' => 'literary', 'is_enabled' => true, 'display_order' => 1,
        ]);
        $itemB = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Quiz', 'participant_type' => 'individual',
            'category' => 'literary', 'is_enabled' => true, 'display_order' => 2,
        ]);

        // Same student enters both items -> two FestParticipant rows, one real person.
        $multiA = $this->individualParticipant($event, $itemA, $school->id);
        $registrationB = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $itemB->id, 'school_id' => $school->id,
            'status' => 'approved', 'submitted_at' => now(),
        ]);
        $multiB = FestParticipant::create([
            'registration_id' => $registrationB->id, 'event_id' => $event->id,
            'student_id' => $multiA->student_id, 'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        // A different student, single item.
        $solo = $this->individualParticipant($event, $itemA, $school->id);

        $service = app(FestCertificateService::class);
        $created = $service->generateParticipationForEvent($event);

        $this->assertCount(2, $created, 'One certificate per distinct student, not per item entry.');

        $multiCert = Certificate::where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', [$multiA->id, $multiB->id])
            ->first();
        $this->assertNotNull($multiCert);
        $this->assertSame($multiA->id, $multiCert->entity_id, 'Anchors to the lower-id participant row.');

        $context = $service->renderContext($multiCert);
        $this->assertSame('Elocution and Quiz', $context['fieldValues']['item_title'], 'Body text lists every item the student entered.');
        $this->assertSame('Elocution and Quiz', $context['fieldValues']['item_details'], 'item_details is an alias of item_title — bare item name(s) only.');
        $this->assertSame(
            'Literary',
            $context['fieldValues']['category_name'],
            'Both items share the same category — deduped to one mention, not repeated per item.',
        );
        $this->assertSame('Individual', $context['fieldValues']['participation_type'], 'Both items share the same type — deduped to one mention.');

        $soloCert = Certificate::where('entity_type', FestParticipant::class)->where('entity_id', $solo->id)->first();
        $soloContext = $service->renderContext($soloCert);
        $this->assertSame('Elocution', $soloContext['fieldValues']['item_title'], 'A single-item student sees just that item, no dangling "and".');
        $this->assertSame('Elocution', $soloContext['fieldValues']['item_details']);
        $this->assertSame('Literary', $soloContext['fieldValues']['category_name']);

        $tally = $service->certificateTally($event);
        $this->assertSame(2, $tally['totals']['participation_certs'], 'Tally total matches generation: 2 distinct students, not 3 item entries.');
    }

    /**
     * FestEventItem::participant_type has 5 real values (see FestTeamSquadRules::ALL_TYPES:
     * individual, team, group, pair, trio), not just 3 — a match statement that only
     * special-cased 'group'/'team' once silently mislabeled pair/trio items as
     * "Individual" in participation_type.
     */
    public function test_participation_type_labels_pair_and_trio_items_correctly(): void
    {
        $event = $this->makeEvent();
        $school = $this->makeSchool($event->tenant_id);

        $pairItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Conversation', 'participant_type' => 'pair',
            'category' => 'general', 'is_enabled' => true,
        ]);
        $trioItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Skit', 'participant_type' => 'trio',
            'category' => 'general', 'is_enabled' => true,
        ]);

        $pairParticipant = $this->individualParticipant($event, $pairItem, $school->id, position: 1);
        $trioParticipant = $this->individualParticipant($event, $trioItem, $school->id, position: 1);

        $service = app(FestCertificateService::class);
        $service->generateForEvent($event);

        $pairCert = Certificate::where('entity_type', FestParticipant::class)->where('entity_id', $pairParticipant->id)->where('cert_type', 'winner')->first();
        $trioCert = Certificate::where('entity_type', FestParticipant::class)->where('entity_id', $trioParticipant->id)->where('cert_type', 'winner')->first();

        $this->assertSame('Pair', $service->renderContext($pairCert)['fieldValues']['participation_type']);
        $this->assertSame('Trio', $service->renderContext($trioCert)['fieldValues']['participation_type']);
    }

    /**
     * Companion to the "same category/type across items" aggregation case — when a
     * person's items genuinely differ in category or type, both must survive the dedup
     * (unique() drops exact repeats, not everything).
     */
    public function test_category_and_type_are_joined_not_deduped_away_when_items_differ(): void
    {
        $event = $this->makeEvent();
        $school = $this->makeSchool($event->tenant_id);

        $soloItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Elocution', 'participant_type' => 'individual',
            'category' => 'literary', 'is_enabled' => true, 'display_order' => 1,
        ]);
        $groupItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Choral Reading', 'participant_type' => 'group',
            'category' => 'music', 'is_enabled' => true, 'display_order' => 2,
        ]);

        $anchor = $this->individualParticipant($event, $soloItem, $school->id);
        $registrationB = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $groupItem->id, 'school_id' => $school->id,
            'status' => 'approved', 'submitted_at' => now(),
        ]);
        FestParticipant::create([
            'registration_id' => $registrationB->id, 'event_id' => $event->id,
            'student_id' => $anchor->student_id, 'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        $service = app(FestCertificateService::class);
        $service->generateParticipationForEvent($event);

        $cert = Certificate::where('entity_type', FestParticipant::class)->where('entity_id', $anchor->id)->where('cert_type', 'participation')->first();
        $fieldValues = $service->renderContext($cert)['fieldValues'];

        $this->assertSame('Literary and Music', $fieldValues['category_name']);
        $this->assertSame('Individual and Group', $fieldValues['participation_type']);
    }
}
