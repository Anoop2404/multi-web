<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestStateNominationSelection;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\StateDomain;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\State\FestStateNominationService;
use App\Services\State\FestStateQualifierPayloadBuilder;
use App\Services\State\FestStateWinnerSheetService;
use App\Services\State\StateSubmissionClient;
use Illuminate\Http\Request;

/**
 * Registering this Sahodaya's winners for the State's items.
 *
 * Item-driven: the page lists the State items, each with this Sahodaya's own winner sheet for it, how
 * many slots the State gives, who is chosen so far, and any tie still to be broken. Picks are saved as
 * they are made — the sheet is a draft until it is registered — and registering is the existing
 * certify-and-push, so there is one path into the State's inbox rather than two.
 */
class FestStateWinnerRegistrationController extends SahodayaAdminController
{
    public function index(Request $request, string $tenantId, FestEvent $event, FestStateWinnerSheetService $sheets, FestStateNominationService $nominations)
    {
        [$program] = $this->context($event);

        $filters = $request->validate([
            'category' => 'nullable|string|max:40',
            'class_group' => 'nullable|string|max:40',
            'only_incomplete' => 'nullable|boolean',
        ]);

        $batch = $nominations->openBatch($program, $event);

        return $this->inertia('Sahodaya/Events/StateWinnerRegistration', [
            'event' => $event->only(['id', 'title', 'state_program_id']),
            'program' => $program->only(['id', 'title']),
            'batch' => $batch->only(['id', 'status', 'maker_id', 'checker_id', 'certified_at']),
            'filters' => $filters,
            'sheet' => $sheets->sheet($program, $event, $filters),
            'readiness' => $sheets->readiness($program, $event),
            'categories' => FestStateProgramItem::where('state_program_id', $program->id)
                ->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'classGroups' => FestStateProgramItem::where('state_program_id', $program->id)
                ->whereNotNull('class_group')->distinct()->orderBy('class_group')->pluck('class_group'),
            'currentUserId' => $request->user()?->id,
            'actionUrls' => $this->actionUrls($tenantId, $event),
        ]);
    }

    public function choose(Request $request, string $tenantId, FestEvent $event, FestStateNominationService $nominations)
    {
        [$program] = $this->context($event);

        $data = $request->validate([
            'candidate' => 'required|array',
            'nomination_type' => 'required|in:primary,reserve',
            'priority_order' => 'nullable|integer|min:1|max:20',
            'note' => 'nullable|string|max:1000',
        ]);

        $batch = $nominations->openBatch($program, $event);

        $nominations->select(
            $batch,
            $data['candidate'],
            $data['nomination_type'],
            (int) ($data['priority_order'] ?? 1),
            $request->user(),
            $data['note'] ?? null,
        );

        return back()->with('success', $data['nomination_type'] === 'primary'
            ? 'Added to this item\'s State entry. Nothing is sent until you register the sheet.'
            : 'Recorded as reserve.');
    }

    public function remove(Request $request, string $tenantId, FestEvent $event, FestStateNominationSelection $selection, FestStateNominationService $nominations)
    {
        [$program] = $this->context($event);
        $batch = $nominations->openBatch($program, $event);

        abort_unless($selection->batch_id === $batch->id, 404);

        $nominations->unselect($selection);

        return back()->with('success', 'Removed from this item\'s State entry.');
    }

    /** A winner who will not travel. Recorded against them, so the sheet says why the next rank goes. */
    public function decline(Request $request, string $tenantId, FestEvent $event, FestStateWinnerSheetService $sheets, FestStateNominationService $nominations)
    {
        [$program] = $this->context($event);

        $data = $request->validate([
            'candidate' => 'required|array',
            'reason' => 'required|string|max:1000',
        ]);

        $sheets->decline($nominations->openBatch($program, $event), $data['candidate'], $data['reason'], $request->user());

        return back()->with('success', 'Recorded. The next rank can now be chosen in their place.');
    }

    public function withdrawDecline(Request $request, string $tenantId, FestEvent $event, FestStateNominationSelection $selection, FestStateWinnerSheetService $sheets, FestStateNominationService $nominations)
    {
        [$program] = $this->context($event);

        $sheets->withdrawDecline($nominations->openBatch($program, $event), $selection);

        return back()->with('success', 'Decline withdrawn.');
    }

    public function autoFill(Request $request, string $tenantId, FestEvent $event, FestStateWinnerSheetService $sheets)
    {
        [$program] = $this->context($event);

        $result = $sheets->autoFill($program, $event, $request->user());

        return back()->with(
            $result['skipped_ties'] ? 'warning' : 'success',
            "{$result['filled']} slot(s) filled from the top of each sheet."
                .($result['skipped_ties']
                    ? ' Left for you to decide, because a tie has to be broken: '.implode(', ', $result['skipped_ties']).'.'
                    : ''),
        );
    }

    /**
     * Register the sheet with State: certify the batch, then push it.
     *
     * Certification is by a second person where the committee has one — the maker/checker rule the
     * nomination workspace already applies — and the same certified batch is what the payload builder
     * reads, so this page and that workspace cannot send two different answers.
     */
    public function register(
        Request $request,
        string $tenantId,
        FestEvent $event,
        FestStateWinnerSheetService $sheets,
        FestStateNominationService $nominations,
        FestStateQualifierPayloadBuilder $builder,
        StateSubmissionClient $client,
        PlatformAuditLogger $audit,
    ) {
        [$program] = $this->context($event);

        $data = $request->validate(['notes' => 'nullable|string|max:2000']);

        $readiness = $sheets->readiness($program, $event);

        // Blocked rather than sent with a guess: an unresolved tie means the sheet does not yet say
        // who is going, and the State cannot resolve it on the Sahodaya's behalf.
        abort_if(! $readiness['can_register'], 422, implode(' ', $readiness['blocking']));
        abort_if($readiness['chosen'] === 0, 422, 'Nothing is chosen yet, so there is nothing to register.');

        abort_if(! $program->state_domain_id, 422, 'This State program has no State address configured, so nothing can be sent to it.');
        $domain = StateDomain::findOrFail($program->state_domain_id);

        $batch = $nominations->openBatch($program, $event);

        if (! $batch->isCertified()) {
            $nominations->certifyCheckerNomination(
                ['batch_id' => $batch->id, 'notes' => $data['notes'] ?? null],
                $request->user(),
            );
        }

        $outbox = $builder->enqueue($program, $event, $this->sahodaya->id, $request->user()?->id);
        $client->send($outbox, $domain);

        $audit->log('fest.state_winners.registered', [
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $event->id,
            'batch_id' => $batch->id,
            'entries' => $readiness['chosen'],
        ]);

        return back()->with(
            'success',
            $outbox->status === 'completed'
                ? "Registered {$readiness['chosen']} winner(s) with State. They appear in the State's submissions queue for scrutiny."
                : "Registration queued — {$readiness['chosen']} winner(s). Check the outbox for delivery.",
        );
    }

    /** @return array{0: FestStateProgram} */
    private function context(FestEvent $event): array
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if(! $event->state_program_id, 422, 'This event is not linked to a State program, so it has no State items to register winners for.');

        return [FestStateProgram::findOrFail($event->state_program_id)];
    }

    /** @return array<string, string> */
    private function actionUrls(string $tenantId, FestEvent $event): array
    {
        $base = "/sahodaya-admin/{$tenantId}/events/{$event->id}/state-winners";

        return [
            'index' => $base,
            'choose' => "{$base}/choose",
            'remove' => "{$base}/selections",
            'decline' => "{$base}/decline",
            'withdrawDecline' => "{$base}/declines",
            'autoFill' => "{$base}/auto-fill",
            'register' => "{$base}/register",
        ];
    }
}
