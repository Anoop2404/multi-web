<?php

namespace App\Http\Controllers\StateAdmin\Fest;

use App\Http\Controllers\Controller;
use App\Models\FestStateProgramItem;
use App\Models\State\StateAppeal;
use App\Models\State\StateCertificate;
use App\Models\State\StateCertificateBatch;
use App\Models\State\StateFestEvent;
use App\Models\State\StateSahodaya;
use App\Services\State\Fest\StateAppealService;
use App\Services\State\Fest\StateCertificateService;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Phases 7 and 9 of the State Kalotsav module — appeals and certificates. */
class StateCertificateController extends Controller
{
    // ── Appeals ────────────────────────────────────────────────────────────────────────────

    public function appeals(Request $request, StateFestEvent $event, StateAppealService $appeals)
    {
        StateScope::assertOwns($event->state_id);

        return Inertia::render('State/Fest/Appeals', $this->shell($request, $event) + [
            'appeals' => $appeals->listFor($event),
            'items' => FestStateProgramItem::where('state_program_id', $event->state_program_id)
                ->orderBy('title')->get(['id', 'item_code', 'title']),
            'actionUrls' => [
                'submit' => "/admin/state/fest/{$event->id}/appeals",
                'decide' => "/admin/state/fest/{$event->id}/appeals/decide",
            ],
        ]);
    }

    public function submitAppeal(Request $request, StateFestEvent $event, StateAppealService $appeals)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'sahodaya_id' => 'nullable|uuid',
            'item_id' => 'nullable|uuid',
            'item_code' => 'nullable|string|max:64',
            'registration_id' => 'nullable|integer',
            'participant_id' => 'nullable|integer',
            'participant_name' => 'nullable|string|max:255',
            'school_name' => 'nullable|string|max:255',
            // An appeal without grounds cannot be decided.
            'grounds' => 'required|string|max:2000',
            'fee_amount' => 'nullable|numeric|min:0',
        ]);

        $appeals->submit($event, $data);

        return back()->with('success', 'Appeal recorded.');
    }

    public function decideAppeal(Request $request, StateFestEvent $event, StateAppealService $appeals)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'appeal_id' => 'required|uuid',
            'outcome' => ['required', Rule::in(['upheld', 'dismissed'])],
            'notes' => 'required|string|max:2000',
            'summary' => 'nullable|string|max:255',
        ]);

        $appeal = StateAppeal::where('state_event_id', $event->id)->findOrFail($data['appeal_id']);

        $result = $appeals->decide($event, $appeal, $data + [
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
        ]);

        $message = "Appeal {$data['outcome']}.";
        if ($result['result_reopened']) {
            $message .= ' The item\'s result was taken off public view for correction.';
        }
        if ($result['certificates_stale']) {
            $message .= " {$result['certificates_stale']} certificate(s) marked stale.";
        }

        return back()->with('success', $message);
    }

    // ── Certificates ───────────────────────────────────────────────────────────────────────

    public function certificates(Request $request, StateFestEvent $event, StateCertificateService $certificates)
    {
        StateScope::assertOwns($event->state_id);

        $filters = $request->validate([
            'type' => 'nullable|string|max:30',
            'sahodaya_id' => 'nullable|uuid',
            'item_id' => 'nullable|uuid',
        ]);

        $type = $filters['type'] ?? 'merit';

        return Inertia::render('State/Fest/Certificates', $this->shell($request, $event) + [
            'types' => StateCertificate::TYPES,
            'filters' => $filters + ['type' => $type],
            'eligibility' => $certificates->eligibility($event, $type, $filters),
            'tally' => $certificates->tally($event),
            'items' => FestStateProgramItem::where('state_program_id', $event->state_program_id)
                ->orderBy('title')->get(['id', 'item_code', 'title']),
            'batches' => StateCertificateBatch::where('state_event_id', $event->id)
                ->orderByDesc('created_at')->limit(20)->get()
                ->map(fn (StateCertificateBatch $b) => [
                    'id' => $b->id, 'type' => $b->type, 'scope' => $b->scope, 'status' => $b->status,
                    'requested' => $b->requested_count, 'generated' => $b->generated_count,
                    'by' => $b->requested_by_name, 'at' => $b->created_at?->toDateTimeString(),
                ]),
            'actionUrls' => [
                'generate' => "/admin/state/fest/{$event->id}/certificates/generate",
                'detectStale' => "/admin/state/fest/{$event->id}/certificates/detect-stale",
            ],
            'baseUrl' => "/admin/state/fest/{$event->id}/certificates",
        ]);
    }

    public function generate(Request $request, StateFestEvent $event, StateCertificateService $certificates)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(StateCertificate::TYPES))],
            'sahodaya_id' => 'nullable|uuid',
            'item_id' => 'nullable|uuid',
        ]);

        $result = $certificates->generate($event, $data['type'], $data, [
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
        ]);

        return back()->with('success', "{$result['generated']} generated"
            .($result['skipped'] ? ", {$result['skipped']} already current" : '').'.');
    }

    public function detectStale(Request $request, StateFestEvent $event, StateCertificateService $certificates)
    {
        StateScope::assertOwns($event->state_id);

        $stale = $certificates->detectStale($event);

        return back()->with(
            $stale->isEmpty() ? 'success' : 'warning',
            $stale->isEmpty()
                ? 'Every certificate still matches its result.'
                : "{$stale->count()} certificate(s) no longer match their result and were marked stale.",
        );
    }

    /** @return array<string, mixed> */
    private function shell(Request $request, StateFestEvent $event): array
    {
        return [
            'event' => [
                'id' => $event->id, 'name' => $event->name, 'status' => $event->status,
                'starts_on' => $event->starts_on?->toDateString(), 'ends_on' => $event->ends_on?->toDateString(),
                'results_published' => (bool) $event->results_published,
                'scoring_locked' => (bool) $event->scoring_locked,
            ],
            'events' => StateScope::apply(StateFestEvent::query())->orderByDesc('starts_on')
                ->get(['id', 'name', 'status'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'status' => $e->status, 'href' => "/admin/state/fest/{$e->id}"]),
            'sahodayas' => StateSahodaya::query()
                ->when(StateScope::shouldScope(), fn ($q) => $q->forState(StateScope::id()))
                ->orderBy('name')->get(['id', 'name', 'district', 'origin']),
            'permissions' => app(StateFestWorkspaceController::class)->permissionsForRequest($request),
        ];
    }
}
