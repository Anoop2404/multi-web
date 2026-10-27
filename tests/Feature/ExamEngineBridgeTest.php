<?php

namespace Tests\Feature;

use App\Models\ExamEngineBooking;
use App\Models\McqExam;
use App\Models\McqProctorEvent;
use App\Models\McqRegistration;
use App\Services\ExamEngine\ExamEngineBookingService;
use App\Services\ExamEngine\ExamEngineBridge;
use App\Services\ExamEngine\ExamEngineEventProcessor;
use App\Services\ExamEngine\ExamEngineRun;
use App\Services\Mcq\McqHallTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\McqFixtures;
use Tests\TestCase;

/** Laravel ↔ exam engine bridge (exam-engine/CONTRACT.md §2, §5, §6) with a faked engine. */
class ExamEngineBridgeTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    private string $launchPublicKey;

    /** @var array<string, array<string, mixed>> path suffix → JSON reply from the fake engine */
    private array $engineReplies = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();

        $pair = sodium_crypto_sign_keypair();
        $this->launchPublicKey = sodium_crypto_sign_publickey($pair);
        config([
            'exam_engine.internal_url'      => 'http://engine.test',
            'exam_engine.internal_token'    => 'test-internal-token',
            'exam_engine.public_url'        => 'https://exam.example.test',
            'exam_engine.launch_secret_key' => base64_encode(sodium_crypto_sign_secretkey($pair)),
            'exam_engine.capacity'          => 2000,
        ]);
        Http::fake(function (Request $request) {
            foreach ($this->engineReplies as $suffix => $reply) {
                if (str_ends_with(parse_url($request->url(), PHP_URL_PATH), $suffix)) {
                    return Http::response($reply);
                }
            }

            return Http::response(['ok' => true]);
        });
    }

    private function engineExam(array $settings = []): McqExam
    {
        $exam = $this->makeOnlineExam([
            'scheduled_at'  => now()->addDays(3),
            'settings_json' => ['engine' => 'engine', 'engine_settings' => $settings],
        ]);
        $bank = $this->makeBank();
        $this->addSingle($bank, 'b', 1);
        $bank->questions()->create(['type' => 'multiple', 'body_text' => 'Primes', 'display_order' => 2,
            'options_json' => [['key' => 'a', 'label' => '2'], ['key' => 'b', 'label' => '4'], ['key' => 'c', 'label' => '7']],
            'answer_json' => ['keys' => ['a', 'c'], 'show_count' => true], 'marks' => 2]);
        $bank->questions()->create(['type' => 'numeric', 'body_text' => '6 × 7', 'display_order' => 3,
            'answer_json' => ['value' => 42, 'tolerance' => 0]]);
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1, 'section_title' => 'Part A']);

        return $exam;
    }

    private function examUrl(McqExam $exam): string
    {
        return "/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$exam->id}";
    }

    public function test_class_templates_cannot_silently_arm_the_default_engine_paper(): void
    {
        $exam = $this->engineExam();
        $exam->update(['settings_json' => array_merge($exam->settings_json, ['paper_assignments' => [['classes' => ['8']]]])]);
        try {
            app(ExamEngineBridge::class)->arm($exam);
            $this->fail('Class templates must not arm the default engine bundle.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('Class paper templates', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_arming_sends_meta_roster_and_an_encrypted_paper_without_answer_keys(): void
    {
        $exam = $this->engineExam();
        [$user, $registration] = $this->registerStudent($exam);
        app(McqHallTicketService::class)->issueBulk($exam);
        $registration->refresh();

        $this->actingAs($this->userWithRole('sahodaya_admin'))
            ->post("{$this->examUrl($exam)}/engine/arm")
            ->assertSessionHas('success');

        $run = rawurlencode(ExamEngineRun::for($exam));
        $meta = $roster = $bundle = null;
        Http::assertSent(function (Request $request) use ($run, &$meta, &$roster, &$bundle) {
            $this->assertSame('Bearer test-internal-token', $request->header('Authorization')[0]);
            match (true) {
                $request->method() === 'PUT' && $request->url() === "http://engine.test/internal/runs/{$run}" => $meta = $request->data(),
                $request->method() === 'POST' && $request->url() === "http://engine.test/internal/runs/{$run}/roster" => $roster = $request->data(),
                $request->method() === 'PUT' && $request->url() === "http://engine.test/internal/runs/{$run}/bundle" => $bundle = $request->data(),
                default => null,
            };

            return true;
        });

        $this->assertSame(3, $meta['question_count']);
        $this->assertSame('either', $meta['entry_mode']);
        $this->assertSame(ExamEngineBridge::passcodeSecret($exam), $meta['passcode_secret']);
        $this->assertSame(['single', 'multiple', 'numeric'], array_values((array) $meta['questions']));
        $this->assertStringEndsWith('Z', $meta['starts_at']);

        $entry = $roster['entries'][0];
        $this->assertSame($registration->id, $entry['reg_id']);
        $this->assertSame($user->getAuthPassword(), $entry['password_hash']);
        $this->assertSame(ExamEngineBridge::passcodeHmac($exam, $registration->id, strtolower($registration->exam_passcode)), $entry['passcode_hmac']);
        $this->assertTrue($entry['allowed']);

        $plain = openssl_decrypt(
            substr(base64_decode($bundle['ciphertext']), 0, -16), 'aes-256-gcm', base64_decode($bundle['key']),
            OPENSSL_RAW_DATA, base64_decode($bundle['iv']), substr(base64_decode($bundle['ciphertext']), -16),
        );
        $paper = json_decode($plain, true);
        $this->assertCount(3, $paper['questions']);
        $this->assertSame('Part A', $paper['questions'][0]['section']);
        $this->assertSame(2, $paper['questions'][1]['show_count']);
        $this->assertStringNotContainsString('correct', $plain);
        $this->assertStringNotContainsString('answer', $plain);

        $exam->refresh();
        $this->assertSame('armed', ExamEngineRun::state($exam));
        $this->assertSame('armed', ExamEngineBooking::where('exam_id', $exam->id)->value('status'));
    }

    public function test_arming_is_refused_with_reasons(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');

        $notEngine = $this->makeOnlineExam(['scheduled_at' => now()->addDay()]);
        $this->actingAs($admin)->post("{$this->examUrl($notEngine)}/engine/arm")->assertSessionHasErrors('engine');

        $noPin = $this->engineExam(['supervision' => 'supervised']);
        $this->actingAs($admin)->post("{$this->examUrl($noPin)}/engine/arm")->assertSessionHasErrors('engine');

        Http::assertNothingSent();
    }

    public function test_capacity_bookings_refuse_overbooked_slots(): void
    {
        config(['exam_engine.capacity' => 2]);
        $exam = $this->engineExam();
        $this->registerStudent($exam);
        ExamEngineBooking::create([
            'tenant_id' => 'other-sahodaya', 'exam_id' => 99, 'title' => 'Other exam',
            'live_starts_at' => $exam->scheduled_at->copy()->subMinutes(30), 'live_ends_at' => $exam->scheduled_at->copy()->addHours(2),
            'expected_count' => 2, 'status' => 'booked',
        ]);

        $this->actingAs($this->userWithRole('sahodaya_admin'))
            ->post("{$this->examUrl($exam)}/engine/arm")
            ->assertSessionHasErrors('engine');

        $plan = app(ExamEngineBookingService::class)->plan(now(), now()->addDays(5));
        $this->assertSame(2, $plan[0]['students']);
        $this->assertSame(1, $plan[0]['nodes']);
        config(['exam_engine.scale_out_threshold' => 2000]);
        $this->assertSame(1, ExamEngineBookingService::nodesFor(2000));
        $this->assertSame(2, ExamEngineBookingService::nodesFor(2001));
        $this->assertSame(4, ExamEngineBookingService::nodesFor(60000));
        $this->assertSame(5, ExamEngineBookingService::nodesFor(60000, passwordOnly: true));
    }

    public function test_engine_settings_validate_and_stay_out_of_offline_exams(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');
        $exam = $this->engineExam();
        $base = array_merge(config('exam_engine.defaults'), ['enabled' => true]);

        $this->actingAs($admin)->put("{$this->examUrl($exam)}/engine", array_merge($base, ['supervision' => 'supervised', 'start_pin' => '']))
            ->assertSessionHasErrors('start_pin');
        $this->actingAs($admin)->put("{$this->examUrl($exam)}/engine", array_merge($base, ['supervision' => 'supervised', 'start_pin' => '4821', 'late_entry_minutes' => 20]))
            ->assertSessionHas('success');
        $this->assertSame('4821', ExamEngineRun::settings($exam->fresh())['start_pin']);
        $this->assertSame(20, ExamEngineRun::settings($exam->fresh())['late_entry_minutes']);

        $offline = $this->makeOnlineExam(['delivery_mode' => 'offline', 'scheduled_at' => now()->addDay()]);
        $this->actingAs($admin)->put("{$this->examUrl($offline)}/engine", $base)->assertSessionHas('error');
        $this->assertFalse(ExamEngineRun::usesEngine($offline->fresh()));

        $this->actingAs($admin)->get("{$this->examUrl($exam)}/question-banks")->assertOk();
    }

    public function test_events_are_applied_idempotently_and_graded_with_the_shared_grader(): void
    {
        $exam = $this->engineExam(['show_score_on_submit' => true]);
        [, $registration] = $this->registerStudent($exam);
        $questions = $exam->questionBanks->first()->questions;
        $run = ExamEngineRun::for($exam);
        $processor = app(ExamEngineEventProcessor::class);

        $events = [
            ['id' => '1-0', 'type' => 'attempt.checked_in', 'run' => $run, 'reg_id' => $registration->id, 'at' => now()->toIso8601String(), 'data' => ['ip' => '10.0.0.5', 'user_agent' => 'Chrome', 'method' => 'passcode']],
            ['id' => '2-0', 'type' => 'attempt.started', 'run' => $run, 'reg_id' => $registration->id, 'at' => now()->toIso8601String(), 'data' => []],
            ['id' => '3-0', 'type' => 'proctor.batch', 'run' => $run, 'reg_id' => $registration->id, 'at' => now()->toIso8601String(), 'data' => ['events' => [['type' => 'tab_hidden', 'at' => now()->toIso8601String()], ['type' => 'offline', 'at' => now()->toIso8601String()], ['type' => 'nonsense']]]],
            ['id' => '4-0', 'type' => 'attempt.action', 'run' => $run, 'reg_id' => $registration->id, 'at' => now()->toIso8601String(), 'data' => ['action' => 'extend', 'minutes' => 5, 'reason' => 'power cut', 'actor' => 'ops:on-call']],
            ['id' => '5-0', 'type' => 'attempt.submitted', 'run' => $run, 'reg_id' => $registration->id, 'at' => now()->toIso8601String(), 'data' => [
                'mode' => 'auto', 'rev' => 9, 'late_changes' => 0, 'receipt' => 'MLP-1-1-AB12',
                'answers' => [(string) $questions[0]->id => 'b', (string) $questions[1]->id => ['a', 'c'], (string) $questions[2]->id => '41'],
            ]],
            ['id' => '6-0', 'type' => 'attempt.started', 'run' => 'missing-tenant:1', 'reg_id' => 1, 'at' => now()->toIso8601String(), 'data' => []],
        ];

        $handled = $processor->process($events);
        $this->assertSame(['1-0', '2-0', '3-0', '4-0', '5-0', '6-0'], collect($handled)->sort()->values()->all());

        $registration->refresh();
        $this->assertNotNull($registration->checked_in_at);
        $this->assertSame('present', $registration->attendance_status);
        $this->assertSame('submitted', $registration->status);
        $this->assertSame('auto', $registration->submit_mode);
        $this->assertSame('MLP-1-1-AB12', $registration->engine_receipt);
        $this->assertSame(5, $registration->extra_time_minutes);
        $this->assertSame(2, McqProctorEvent::where('registration_id', $registration->id)->count());
        $this->assertSame(2, $registration->mark->correct_count);
        $this->assertSame(1, $registration->mark->wrong_count);
        $this->assertEquals(3, $registration->mark->score);
        $this->assertEquals(3, $processor->pendingResults()[$run][$registration->id]['score']);

        // Redelivery changes nothing.
        $processor->process([$events[4]]);
        $this->assertSame(1, \App\Models\McqMark::where('registration_id', $registration->id)->count());
        $this->assertEquals(3, $registration->fresh()->mark->score);
    }

    public function test_consume_command_claims_applies_and_acks(): void
    {
        $exam = $this->engineExam();
        [, $registration] = $this->registerStudent($exam);
        $this->engineReplies['/internal/events/claim'] = ['events' => [[
            'id' => '11-0', 'type' => 'attempt.checked_in', 'run' => ExamEngineRun::for($exam), 'reg_id' => $registration->id,
            'at' => now()->toIso8601String(), 'data' => ['ip' => '1.2.3.4'],
        ]]];
        $this->engineReplies['/internal/events/ack'] = ['acked' => 1];

        $this->assertSame(0, Artisan::call('exam-engine:consume', ['--once' => true, '--consumer' => 'test']));

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/internal/events/ack') && $r->data()['ids'] === ['11-0']);
        $this->assertNotNull($registration->fresh()->checked_in_at);
    }

    public function test_portal_enter_exam_signs_a_single_use_launch_token(): void
    {
        $exam = $this->engineExam();
        [$user, $registration] = $this->registerStudent($exam);
        $base = "/portal/student/{$this->school->id}/mcq/{$registration->id}/exam";

        $this->actingAs($user)->get($base)->assertRedirect()->assertSessionHasErrors('exam'); // not armed yet

        $exam->update(['settings_json' => array_merge($exam->settings_json, ['engine_state' => 'armed'])]);
        $location = $this->actingAs($user)->get($base)->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://exam.example.test/', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        [$payload, $signature] = explode('.', $query['launch']);
        $decode = fn ($v) => base64_decode(strtr($v, '-_', '+/').str_repeat('=', (4 - strlen($v) % 4) % 4));
        $json = $decode($payload);
        $this->assertTrue(sodium_crypto_sign_verify_detached($decode($signature), $json, $this->launchPublicKey));
        $claims = json_decode($json, true);
        $this->assertSame(ExamEngineRun::for($exam), $claims['run']);
        $this->assertSame($registration->id, $claims['reg_id']);
        $this->assertLessThanOrEqual(now()->addSeconds(120)->getTimestamp(), $claims['exp']);

        // The in-app exam refuses engine exams.
        $this->actingAs($user)->post("/portal/student/{$this->school->id}/mcq/{$registration->id}/start")->assertSessionHasErrors('exam');
    }

    public function test_password_change_pushes_the_new_hash_to_an_armed_roster(): void
    {
        $exam = $this->engineExam();
        [$user, $registration] = $this->registerStudent($exam);
        $exam->update(['settings_json' => array_merge($exam->settings_json, ['engine_state' => 'armed', 'engine_bundle_version' => 1])]);

        $user->update(['password' => bcrypt('a-new-password')]);

        $run = rawurlencode(ExamEngineRun::for($exam));
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && $r->url() === "http://engine.test/internal/runs/{$run}/roster/{$registration->id}"
            && password_verify('a-new-password', $r->data()['password_hash']));
    }

    public function test_in_app_auto_submit_skips_engine_exams_and_keeps_autosaved_answers(): void
    {
        $engineExam = $this->engineExam();
        [, $engineReg] = $this->registerStudent($engineExam);
        $engineReg->update(['status' => 'started', 'started_at' => now()->subHours(3)]);

        $inApp = $this->makeOnlineExam(['scheduled_at' => now()->subHours(3), 'duration_minutes' => 30]);
        $bank = $this->makeBank('active', 'In-app bank');
        $question = $this->addSingle($bank, 'b');
        $inApp->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);
        [, $inAppReg] = $this->registerStudent($inApp);
        $inAppReg->update(['status' => 'started', 'started_at' => now()->subHours(2), 'draft_answers' => [(string) $question->id => 'b']]);

        Artisan::call('mcq:auto-submit-expired');

        $this->assertSame('started', $engineReg->fresh()->status);
        $this->assertSame('submitted', $inAppReg->fresh()->status);
        $this->assertSame(1, $inAppReg->fresh()->mark->correct_count);
        $this->assertInstanceOf(McqRegistration::class, $inAppReg);
    }
}
