<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\State\StateAppeal;
use App\Models\State\StateCertificate;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateItemResult;
use App\Models\State\StateSahodaya;
use App\Services\State\Fest\StateAppealService;
use App\Services\State\Fest\StateCertificateService;
use App\Services\State\Fest\StateResultService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phases 7 and 9 of the State Kalotsav module — appeals and certificates.
 *
 * These are one story from opposite ends: an appeal is how a published result changes, and a changed
 * result is what makes a certificate wrong.
 */
class StateAppealCertificateTest extends TestCase
{
    use RefreshDatabase;

    private PlatformState $state;

    private StateFestEvent $event;

    private FestStateProgramItem $item;

    private StateSahodaya $sahodaya;

    private array $users = [];

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
            'tenant_id' => (string) Str::uuid(), 'origin' => StateSahodaya::ORIGIN_MANAGED,
        ]);
    }

    private function competitor(string $name, float $score, string $school = 'St Joseph HSS'): StateFestParticipant
    {
        $registration = StateFestRegistration::create([
            'state_event_id' => $this->event->id, 'sahodaya_id' => $this->sahodaya->id,
            'sahodaya_name' => 'Malappuram Sahodaya', 'school_id' => Str::slug($school), 'school_name' => $school,
            'item_id' => $this->item->id, 'item_code' => 'LM01', 'status' => 'approved',
        ]);

        $participant = StateFestParticipant::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'student_name' => $name, 'class_name' => 'Class 10',
        ]);

        StateFestMark::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'participant_id' => $participant->id, 'score' => $score, 'grade' => 'A', 'status' => 'aggregated',
        ]);

        return $participant;
    }

    private function publishItem(): void
    {
        app(StateResultService::class)->computeItem($this->event, $this->item);
        app(StateResultService::class)->publishItem($this->event, $this->item);
    }

    private function certificates(): StateCertificateService
    {
        return app(StateCertificateService::class);
    }

    private function appeals(): StateAppealService
    {
        return app(StateAppealService::class);
    }

    private function user(string $role = 'state_admin'): PlatformUser
    {
        return $this->users[$role] ??= tap(PlatformUser::create([
            'name' => 'Registrar', 'email' => $role.'@example.test', 'username' => $role,
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]), fn ($u) => $u->assignRole($role));
    }

    // ── Certificates ───────────────────────────────────────────────────────────────────────

    public function test_nobody_is_eligible_before_the_result_is_published(): void
    {
        $this->competitor('Athira', 90);

        $rows = $this->certificates()->eligibility($this->event, 'merit');

        $this->assertFalse($rows[0]['eligible']);
        $this->assertStringContainsString('not published', $rows[0]['reason']);
    }

    public function test_merit_certificates_go_to_the_first_three_and_say_why_not_to_others(): void
    {
        $this->competitor('First', 95);
        $this->competitor('Second', 90);
        $this->competitor('Third', 85);
        $this->competitor('Fourth', 80);
        $this->publishItem();

        $rows = $this->certificates()->eligibility($this->event, 'merit')->keyBy('name');

        $this->assertTrue($rows['First']['eligible']);
        $this->assertTrue($rows['Third']['eligible']);
        $this->assertFalse($rows['Fourth']['eligible']);
        $this->assertStringContainsString('Placed 4', $rows['Fourth']['reason']);
    }

    public function test_a_certificate_carries_both_the_sahodaya_and_the_school(): void
    {
        $this->competitor('Athira', 95);
        $this->publishItem();

        $this->certificates()->generate($this->event, 'merit');

        $certificate = StateCertificate::sole();
        $this->assertSame('Malappuram Sahodaya', $certificate->sahodaya_name);
        $this->assertSame('St Joseph HSS', $certificate->school_name);
        $this->assertSame(1, $certificate->position);
        $this->assertNotEmpty($certificate->certificate_number);
        $this->assertNotEmpty($certificate->verification_code);
    }

    public function test_regenerating_an_unchanged_certificate_does_not_issue_a_second_number(): void
    {
        $this->competitor('Athira', 95);
        $this->publishItem();

        $first = $this->certificates()->generate($this->event, 'merit');
        $second = $this->certificates()->generate($this->event, 'merit');

        $this->assertSame(1, $first['generated']);
        $this->assertSame(0, $second['generated']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, StateCertificate::count());
    }

    public function test_a_certificate_can_be_verified_by_number_or_code(): void
    {
        $this->competitor('Athira', 95);
        $this->publishItem();
        $this->certificates()->generate($this->event, 'merit');

        $certificate = StateCertificate::sole();

        $this->assertSame($certificate->id, $this->certificates()->verify($certificate->certificate_number)?->id);
        $this->assertSame($certificate->id, $this->certificates()->verify($certificate->verification_code)?->id);
        $this->assertNull($this->certificates()->verify('not-a-certificate'));
    }

    public function test_a_changed_result_makes_the_certificate_stale(): void
    {
        $athira = $this->competitor('Athira', 95);
        $this->competitor('Rahul', 90);
        $this->publishItem();
        $this->certificates()->generate($this->event, 'merit');

        // The mark is corrected, so the ranking changes under a certificate already printed.
        StateFestMark::where('participant_id', $athira->id)->update(['score' => 60]);
        app(StateResultService::class)->computeItem($this->event, $this->item);

        $stale = $this->certificates()->detectStale($this->event);

        $this->assertGreaterThan(0, $stale->count());
        $this->assertSame(StateCertificate::STALE, StateCertificate::where('participant_id', $athira->id)->value('status'));
    }

    public function test_a_corrected_name_also_makes_a_certificate_stale(): void
    {
        // The fingerprint deliberately covers the printed name, not only the result.
        $athira = $this->competitor('Athrra Menon', 95);
        $this->publishItem();
        $this->certificates()->generate($this->event, 'merit');

        StateCertificate::where('participant_id', $athira->id)->update(['recipient_name' => 'Athira Menon']);

        $this->assertGreaterThan(0, $this->certificates()->detectStale($this->event)->count());
    }

    public function test_regenerating_a_stale_certificate_supersedes_rather_than_overwrites(): void
    {
        // The old number stays resolvable, so verification can say it was replaced.
        $athira = $this->competitor('Athira', 95);
        $this->publishItem();
        $this->certificates()->generate($this->event, 'merit');

        $original = StateCertificate::sole();
        $original->forceFill(['status' => StateCertificate::STALE])->save();

        $this->certificates()->generate($this->event, 'merit');

        $this->assertSame(StateCertificate::SUPERSEDED, $original->fresh()->status);
        $this->assertSame(2, StateCertificate::count());
        $this->assertNotSame(
            $original->certificate_number,
            StateCertificate::where('status', StateCertificate::GENERATED)->value('certificate_number'),
            'A regenerated certificate gets its own number.',
        );
    }

    // ── Appeals ────────────────────────────────────────────────────────────────────────────

    public function test_upholding_an_appeal_reopens_the_result_and_stales_its_certificates(): void
    {
        $athira = $this->competitor('Athira', 95);
        $this->publishItem();
        $this->certificates()->generate($this->event, 'merit');

        $appeal = $this->appeals()->submit($this->event, [
            'item_id' => $this->item->id, 'item_code' => 'LM01',
            'participant_id' => $athira->id, 'participant_name' => 'Athira',
            'school_name' => 'St Joseph HSS', 'grounds' => 'Marks mis-totalled', 'fee_amount' => 500,
        ]);

        $outcome = $this->appeals()->decide($this->event, $appeal, [
            'outcome' => 'upheld', 'notes' => 'Recount confirmed the error.', 'user_name' => 'Registrar',
        ]);

        $this->assertTrue($outcome['result_reopened']);
        $this->assertSame(1, $outcome['certificates_stale']);
        // Off public view until the office republishes the corrected ranking.
        $this->assertSame(StateItemResult::PROVISIONAL, StateItemResult::sole()->status);
        $this->assertSame(StateCertificate::STALE, StateCertificate::sole()->status);
        // An upheld appeal refunds the fee.
        $this->assertSame('refunded', $outcome['appeal']->fee_status);
    }

    public function test_a_dismissed_appeal_forfeits_the_fee_and_leaves_the_result_alone(): void
    {
        $athira = $this->competitor('Athira', 95);
        $this->publishItem();

        $appeal = $this->appeals()->submit($this->event, [
            'item_id' => $this->item->id, 'participant_id' => $athira->id,
            'grounds' => 'Disagree with the panel', 'fee_amount' => 500,
        ]);

        $outcome = $this->appeals()->decide($this->event, $appeal, [
            'outcome' => 'dismissed', 'notes' => 'The panel applied the criteria correctly.',
        ]);

        $this->assertSame('forfeited', $outcome['appeal']->fee_status);
        $this->assertFalse($outcome['result_reopened']);
        $this->assertSame(StateItemResult::PUBLISHED, StateItemResult::sole()->status);
    }

    public function test_a_decision_without_reasons_is_refused(): void
    {
        $appeal = $this->appeals()->submit($this->event, ['grounds' => 'Something']);

        $this->expectException(ValidationException::class);
        $this->appeals()->decide($this->event, $appeal, ['outcome' => 'upheld']);
    }

    public function test_an_appeal_cannot_be_decided_twice(): void
    {
        $appeal = $this->appeals()->submit($this->event, ['grounds' => 'Something']);
        $this->appeals()->decide($this->event, $appeal, ['outcome' => 'dismissed', 'notes' => 'No grounds.']);

        $this->expectException(ValidationException::class);
        $this->appeals()->decide($this->event, $appeal->fresh(), ['outcome' => 'upheld', 'notes' => 'Changed my mind.']);
    }

    public function test_a_locked_result_is_not_reopened_by_an_appeal(): void
    {
        // Locking is a deliberate statement that the result is final; unlocking must be a person's act.
        $athira = $this->competitor('Athira', 95);
        $this->publishItem();
        app(StateResultService::class)->lockItem($this->event, $this->item);

        $appeal = $this->appeals()->submit($this->event, [
            'item_id' => $this->item->id, 'participant_id' => $athira->id, 'grounds' => 'Late appeal',
        ]);

        $outcome = $this->appeals()->decide($this->event, $appeal, ['outcome' => 'upheld', 'notes' => 'Accepted.']);

        $this->assertFalse($outcome['result_reopened']);
        $this->assertSame(StateItemResult::LOCKED, StateItemResult::sole()->status);
    }

    // ── Screens ────────────────────────────────────────────────────────────────────────────

    public function test_the_screens_render_and_are_gated_by_capability(): void
    {
        $this->competitor('Athira', 95);
        $this->publishItem();
        $url = "http://superadmin.test/admin/state/fest/{$this->event->id}";

        $this->actingAs($this->user(), 'platform')->get("{$url}/appeals")->assertOk();
        $this->actingAs($this->user(), 'platform')->get("{$url}/certificates")->assertOk();

        // A certificate operator generates certificates but must not touch appeals or results.
        $operator = $this->user('state_certificate_operator');
        $this->actingAs($operator, 'platform')->get("{$url}/certificates")->assertOk();
        $this->actingAs($operator, 'platform')->get("{$url}/appeals")->assertForbidden();
        $this->actingAs($operator, 'platform')->get("{$url}/results")->assertForbidden();
    }

    public function test_the_certificate_tally_report_is_now_available(): void
    {
        $this->competitor('Athira', 95);
        $this->publishItem();
        $this->certificates()->generate($this->event, 'merit');

        $this->assertTrue(\App\Support\StateFestReportCatalog::isAvailable('certificate-tally'));

        $page = $this->actingAs($this->user(), 'platform')
            ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/reports/certificate-tally")
            ->assertOk()->viewData('page');

        $this->assertSame(['Sahodaya', 'School', 'Type', 'Issued', 'Stale', 'Printed'], $page['props']['headers']);
    }
}
