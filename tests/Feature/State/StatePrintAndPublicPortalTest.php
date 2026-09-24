<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\State\StateCertificate;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateItemSchedule;
use App\Models\State\StateSahodaya;
use App\Models\State\StateVenue;
use App\Services\State\Fest\StateCertificateService;
use App\Services\State\Fest\StateEventSettings;
use App\Services\State\Fest\StatePrintService;
use App\Services\State\Fest\StatePublicPortalService;
use App\Services\State\Fest\StateResultService;
use App\Support\StateFestReportCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 5's printed sheets and Phase 7's public portal.
 *
 * Grouped in one file because they are the two ways this module's data leaves the State office — on
 * paper and on the web — and both are governed by rules about what must NOT appear: a judge sheet
 * must not name a Sahodaya, and an unpublished result must not reach the portal.
 */
class StatePrintAndPublicPortalTest extends TestCase
{
    use RefreshDatabase;

    private PlatformState $state;

    private StateFestEvent $event;

    private FestStateProgramItem $item;

    private StateSahodaya $sahodaya;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Artisan::call('state:migrate');

        $this->state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);
        $program = FestStateProgram::create([
            'title' => 'Kerala State Kalotsavam 2026', 'state_id' => $this->state->id,
            'event_type' => 'kalolsavam', 'conduct_levels' => ['state'], 'status' => 'published',
        ]);
        $this->event = StateFestEvent::create([
            'state_program_id' => $program->id, 'state_id' => $this->state->id,
            'name' => 'State Finals', 'slug' => 'finals', 'status' => 'active',
        ]);
        $this->item = FestStateProgramItem::create([
            'state_program_id' => $program->id, 'title' => 'Light Music', 'item_code' => 'LM01', 'qualify_count' => 3,
        ]);
        $this->sahodaya = StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->state->id, 'name' => 'Malappuram Sahodaya',
            'district' => 'Malappuram', 'tenant_id' => (string) Str::uuid(), 'origin' => StateSahodaya::ORIGIN_MANAGED,
        ]);
    }

    private function competitor(string $name, ?string $chest = null, ?float $score = null, string $school = 'St Joseph HSS'): StateFestParticipant
    {
        $registration = StateFestRegistration::create([
            'state_event_id' => $this->event->id, 'sahodaya_id' => $this->sahodaya->id,
            'sahodaya_name' => $this->sahodaya->name, 'school_id' => Str::slug($school), 'school_name' => $school,
            'item_id' => $this->item->id, 'item_code' => 'LM01', 'status' => 'approved',
        ]);

        $participant = StateFestParticipant::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'student_name' => $name, 'class_name' => 'Class 10', 'chest_number' => $chest,
        ]);

        if ($score !== null) {
            StateFestMark::create([
                'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
                'participant_id' => $participant->id, 'score' => $score, 'grade' => 'A', 'status' => 'aggregated',
            ]);
        }

        return $participant;
    }

    private function print(): StatePrintService
    {
        return app(StatePrintService::class);
    }

    private function portal(): StatePublicPortalService
    {
        return app(StatePublicPortalService::class);
    }

    private function publish(array $visibility = []): void
    {
        app(StateResultService::class)->computeItem($this->event, $this->item);
        app(StateResultService::class)->publishItem($this->event, $this->item);
        $this->event->forceFill(['results_published' => true])->save();

        app(StateEventSettings::class)->update($this->event->fresh(), $visibility);
        $this->event = $this->event->fresh();
    }

    // ── Printed sheets ─────────────────────────────────────────────────────────────────────

    public function test_an_attendance_sheet_is_grouped_by_item_and_ordered_by_chest_number(): void
    {
        $this->competitor('Bindu', '205');
        $this->competitor('Anil', '201');

        $sheet = $this->print()->attendanceSheet($this->event);

        $this->assertCount(1, $sheet['groups']);
        $this->assertSame('LM01', $sheet['groups'][0]['item_code']);
        // Row shape is [Sl, Chest, Participants, ...]: chest 201 is called before 205.
        $this->assertSame('201', $sheet['groups'][0]['rows'][0][1]);
        $this->assertSame('205', $sheet['groups'][0]['rows'][1][1]);
    }

    public function test_the_attendance_sheet_carries_both_the_sahodaya_and_the_school(): void
    {
        $this->competitor('Anil', '201');

        $row = $this->print()->attendanceSheet($this->event)['groups'][0]['rows'][0];

        $this->assertContains('Malappuram Sahodaya', $row);
        $this->assertContains('St Joseph HSS', $row);
    }

    public function test_a_judge_sheet_never_names_the_sahodaya_or_the_school(): void
    {
        $this->competitor('Anil', '201');

        $sheet = $this->print()->judgeSheet($this->event);
        $flat = json_encode($sheet);

        $this->assertStringNotContainsString('Malappuram', $flat);
        $this->assertStringNotContainsString('St Joseph', $flat);
        $this->assertStringNotContainsString('Anil', $flat);
        // The chest number is all the panel gets.
        $this->assertStringContainsString('201', $flat);
    }

    public function test_a_sheet_shows_the_scheduled_time_and_venue_when_there_is_one(): void
    {
        $this->competitor('Anil', '201');

        $venue = StateVenue::create([
            'state_event_id' => $this->event->id, 'state_id' => $this->state->id,
            'name' => 'Town Hall Stage', 'kind' => 'stage',
        ]);
        StateItemSchedule::create([
            'state_event_id' => $this->event->id, 'state_id' => $this->state->id,
            'item_id' => $this->item->id, 'item_code' => 'LM01', 'venue_id' => $venue->id,
            'scheduled_on' => '2026-01-12', 'starts_at' => '10:30:00',
        ]);

        $group = $this->print()->attendanceSheet($this->event)['groups'][0];

        $this->assertSame('2026-01-12', $group['when']);
        $this->assertSame('10:30', $group['starts_at']);
        $this->assertSame('Town Hall Stage', $group['venue']);
    }

    public function test_one_card_per_person_listing_every_item_and_its_own_chest_number(): void
    {
        $this->competitor('Anil', '201');

        // Chest numbers are unique per event, so the same person entered for a second item holds a
        // second number — and both belong on one card.
        $second = FestStateProgramItem::create([
            'state_program_id' => $this->event->state_program_id, 'title' => 'Classical Dance',
            'item_code' => 'CD01', 'qualify_count' => 3,
        ]);
        $registration = StateFestRegistration::create([
            'state_event_id' => $this->event->id, 'sahodaya_id' => $this->sahodaya->id,
            'sahodaya_name' => $this->sahodaya->name, 'school_id' => 'st-joseph-hss', 'school_name' => 'St Joseph HSS',
            'item_id' => $second->id, 'item_code' => 'CD01', 'status' => 'approved',
        ]);
        StateFestParticipant::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'student_name' => 'Anil', 'class_name' => 'Class 10', 'chest_number' => '202',
        ]);

        $cards = $this->print()->cards($this->event);

        $this->assertCount(1, $cards);
        $this->assertSame('Anil', $cards[0]['name']);
        $this->assertSame([
            ['item_code' => 'CD01', 'chest_number' => '202'],
            ['item_code' => 'LM01', 'chest_number' => '201'],
        ], $cards[0]['entries']);
        // No single big number when there are two: printing one of them would be a wrong number.
        $this->assertNull($cards[0]['chest_number']);
    }

    public function test_a_single_entry_card_carries_one_prominent_chest_number(): void
    {
        $this->competitor('Anil', '201');

        $card = $this->print()->cards($this->event)[0];

        $this->assertSame('201', $card['chest_number']);
        $this->assertSame('Malappuram Sahodaya', $card['sahodaya']);
        $this->assertSame('St Joseph HSS', $card['school']);
    }

    public function test_two_people_with_the_same_name_from_different_schools_get_their_own_cards(): void
    {
        $this->competitor('Anjali', '201', null, 'St Joseph HSS');
        $this->competitor('Anjali', '202', null, 'Holy Family HSS');

        $cards = $this->print()->cards($this->event);

        $this->assertCount(2, $cards);
        $this->assertSame(['Holy Family HSS', 'St Joseph HSS'], $cards->pluck('school')->sort()->values()->all());
    }

    public function test_every_printed_sheet_is_available_in_the_catalog(): void
    {
        foreach (['attendance-sheet', 'timesheet', 'judge-sheet', 'green-room-sheet', 'participant-cards'] as $id) {
            $definition = StateFestReportCatalog::find($id);

            $this->assertNotNull($definition, "{$id} is missing from the catalog.");
            $this->assertTrue($definition['available'], "{$id} is still blocked.");
        }
    }

    // ── Public portal ──────────────────────────────────────────────────────────────────────

    public function test_nothing_is_public_until_it_is_switched_on(): void
    {
        $this->competitor('Anil', '201', 90);
        $this->publish();

        $visibility = $this->portal()->visibility($this->event);

        $this->assertFalse($visibility['schedule']);
        $this->assertFalse($visibility['results']);
        $this->assertFalse($visibility['ranking']);
    }

    public function test_the_public_switch_alone_does_not_release_results(): void
    {
        $this->competitor('Anil', '201', 90);
        app(StateResultService::class)->computeItem($this->event, $this->item);
        app(StateResultService::class)->publishItem($this->event, $this->item);
        // The event itself is not published, only the item and the public toggle.
        app(StateEventSettings::class)->update($this->event, ['public_results_visible' => true]);

        $this->assertFalse($this->portal()->visibility($this->event->fresh())['results']);
    }

    public function test_published_results_show_placings_but_never_scores(): void
    {
        $this->competitor('Winner', '201', 95);
        $this->competitor('Runner', '202', 90);
        $this->publish(['public_results_visible' => true]);

        $results = $this->portal()->results($this->event);

        $this->assertCount(1, $results);
        $this->assertSame(1, $results[0]['winners'][0]['position']);
        $this->assertSame('Winner', $results[0]['winners'][0]['participants']);
        $this->assertStringNotContainsString('95', json_encode($results[0]['winners']));
    }

    public function test_an_unpublished_item_stays_out_of_the_public_results(): void
    {
        $this->competitor('Winner', '201', 95);
        $this->publish(['public_results_visible' => true]);

        $other = FestStateProgramItem::create([
            'state_program_id' => $this->event->state_program_id, 'title' => 'Mono Act',
            'item_code' => 'MA01', 'qualify_count' => 3,
        ]);
        $registration = StateFestRegistration::create([
            'state_event_id' => $this->event->id, 'sahodaya_id' => $this->sahodaya->id,
            'sahodaya_name' => $this->sahodaya->name, 'school_id' => 'x', 'school_name' => 'X HSS',
            'item_id' => $other->id, 'item_code' => 'MA01', 'status' => 'approved',
        ]);
        $participant = StateFestParticipant::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'student_name' => 'Secret', 'class_name' => 'Class 9', 'chest_number' => '301',
        ]);
        StateFestMark::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'participant_id' => $participant->id, 'score' => 88, 'position' => 1, 'status' => 'aggregated',
        ]);

        $this->assertStringNotContainsString('Secret', json_encode($this->portal()->results($this->event)));
    }

    public function test_the_public_pages_404_until_released(): void
    {
        $this->competitor('Anil', '201', 90);
        $this->publish();

        $this->get("/state/kalotsav/{$this->event->id}/results")->assertNotFound();
        $this->get("/state/kalotsav/{$this->event->id}/ranking")->assertNotFound();

        app(StateEventSettings::class)->update($this->event, ['public_results_visible' => true]);

        $this->get("/state/kalotsav/{$this->event->id}/results")->assertOk();
    }

    public function test_the_public_pages_need_no_login(): void
    {
        $this->competitor('Anil', '201', 90);
        $this->publish(['public_ranking_visible' => true]);

        $this->get('/state/kalotsav')->assertOk();
        $this->get("/state/kalotsav/{$this->event->id}/ranking")
            ->assertOk()
            ->assertSee('Malappuram Sahodaya');
    }

    public function test_a_sahodaya_page_breaks_its_points_down_by_school(): void
    {
        $this->competitor('Anil', '201', 95, 'St Joseph HSS');
        $this->competitor('Bindu', '202', 90, 'Holy Family HSS');
        $this->publish(['public_ranking_visible' => true]);

        $page = $this->portal()->sahodaya($this->event, $this->sahodaya);

        $this->assertSame('Malappuram Sahodaya', $page['sahodaya']['name']);
        $this->assertCount(2, $page['schools']);
        $this->assertSame(1, $page['standing']['rank']);
    }

    // ── Certificate verification ───────────────────────────────────────────────────────────

    public function test_a_valid_certificate_verifies_with_both_names(): void
    {
        $this->competitor('Anil', '201', 95);
        $this->publish();

        app(StateCertificateService::class)->generate($this->event, 'merit');
        $certificate = StateCertificate::first();

        $result = $this->portal()->verify($certificate->verification_code);

        $this->assertTrue($result['valid']);
        $this->assertSame('Anil', $result['certificate']['recipient']);
        $this->assertSame('Malappuram Sahodaya', $result['certificate']['sahodaya']);
        $this->assertSame('St Joseph HSS', $result['certificate']['school']);
    }

    public function test_a_stale_certificate_verifies_as_needing_attention_not_as_valid(): void
    {
        $this->competitor('Anil', '201', 95);
        $this->publish();
        app(StateCertificateService::class)->generate($this->event, 'merit');

        $certificate = StateCertificate::first();
        $certificate->forceFill(['status' => StateCertificate::STALE])->save();

        $result = $this->portal()->verify($certificate->verification_code);

        $this->assertTrue($result['found']);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('corrected', $result['message']);
    }

    public function test_an_unknown_code_answers_plainly_rather_than_erroring(): void
    {
        $result = $this->portal()->verify('NOT-A-REAL-CODE');

        $this->assertFalse($result['found']);
        $this->assertFalse($result['valid']);
    }

    public function test_verification_is_reachable_without_publishing_anything(): void
    {
        $this->competitor('Anil', '201', 95);
        $this->publish();
        app(StateCertificateService::class)->generate($this->event, 'merit');
        $certificate = StateCertificate::first();

        // No public_* setting is on, and the page still confirms the certificate: one in someone's
        // hand is already public.
        $this->get('/state/certificates/verify/'.$certificate->verification_code)
            ->assertOk()
            ->assertSee('Anil');
    }

    private function user(string $role = 'state_admin'): PlatformUser
    {
        return tap(PlatformUser::create([
            'name' => 'Registrar', 'email' => $role.'@example.test', 'username' => $role,
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]), fn ($u) => $u->assignRole($role));
    }
}
