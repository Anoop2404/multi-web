<?php

namespace Tests\Unit\Services\Events;

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
            'student_id' => 1, 'participant_type' => 'student', 'participant_role' => $role,
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
}
