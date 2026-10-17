<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestStateNominationBatch;
use App\Models\FestStateNominationSelection;
use App\Models\FestStateProgram;
use App\Services\State\FestStateExportService;
use App\Services\State\FestStateSlotCollectionService;
use Illuminate\Http\Request;

/**
 * The parent admin's page for getting a confirmed winner list from schools before State
 * registration opens — see FestStateSlotCollectionService for what "opening" and "approving" do.
 * This sits alongside, not instead of, the existing State Winner Registration page: opening
 * collection here fills that same sheet, and once approved the admin registers with State from
 * there exactly as before.
 */
class FestStateSlotCollectionController extends SahodayaAdminController
{
    /**
     * Every hub event this Sahodaya has linked to a State program, one place to reach any of
     * them instead of having to already be on that event's own Levels tab. Includes events from
     * every fest program (Kalotsav, Sports Meet, ...), not just Kalotsav — a State program can be
     * attached to any of them.
     */
    public function overview(string $tenantId)
    {
        $events = FestEvent::where('tenant_id', $this->sahodaya->id)
            ->whereNull('parent_event_id')
            ->whereNotNull('state_program_id')
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'event_type', 'event_start', 'event_end', 'state_program_id',
                'state_slot_collection_open', 'state_slot_collection_opened_at', 'state_slot_collection_approved_at']);

        $programs = FestStateProgram::whereIn('id', $events->pluck('state_program_id')->unique())
            ->get(['id', 'title'])->keyBy('id');

        $batchesByEvent = FestStateNominationBatch::whereIn('hub_event_id', $events->pluck('id'))
            ->get()->keyBy('hub_event_id');

        $rows = $events->map(function (FestEvent $event) use ($programs, $batchesByEvent) {
            $batch = $batchesByEvent->get($event->id);
            $primaries = $batch ? $batch->selections()->where('nomination_type', 'primary')->where('status', 'selected')->get() : collect();

            return [
                'id' => $event->id,
                'title' => $event->title,
                'event_type' => $event->event_type,
                'program_title' => $programs->get($event->state_program_id)?->title,
                'event_start' => $event->event_start,
                'event_end' => $event->event_end,
                'collection_open' => (bool) $event->state_slot_collection_open,
                'collection_opened_at' => $event->state_slot_collection_opened_at,
                'collection_approved_at' => $event->state_slot_collection_approved_at,
                'pending' => $primaries->where('school_response', 'pending')->count(),
                'accepted' => $primaries->where('school_response', 'accepted')->count(),
                'url' => "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/state-slot-collection",
            ];
        });

        return $this->inertia('Sahodaya/Events/StateSlotCollectionOverview', [
            'events' => $rows,
            'sahodaya' => $this->sahodaya,
        ]);
    }

    public function index(string $tenantId, FestEvent $event, FestStateSlotCollectionService $collection)
    {
        $program = $this->context($event);

        $batch = FestStateNominationBatch::where('hub_event_id', $event->id)->first();
        $selections = $batch ? $batch->selections()->where('nomination_type', 'primary')->get() : collect();
        $schoolIds = $selections->pluck('school_id')->filter()->unique()->values();
        $schools = \App\Models\Tenant::whereIn('id', $schoolIds)->get()->keyBy('id');
        $schoolUsers = \App\Models\User::whereIn('tenant_id', $schoolIds)->whereNotNull('email')->get()->groupBy('tenant_id');

        $schoolsSummary = $schoolIds->map(function ($schId) use ($schools, $selections, $schoolUsers) {
            $sch = $schools->get($schId);
            $schSelections = $selections->where('school_id', $schId);
            $payload = is_array($sch?->application_payload) ? $sch->application_payload : [];
            $email = $payload['school_email'] ?? $payload['contact_email'] ?? $schoolUsers->get($schId)?->first()?->email;

            return [
                'school_id' => $schId,
                'school_name' => $sch?->name ?? $schSelections->first()?->school_name ?? "School {$schId}",
                'email' => $email,
                'has_email' => ! empty($email),
                'pending_count' => $schSelections->where('status', 'selected')->where('school_response', 'pending')->count(),
                'accepted_count' => $schSelections->where('status', 'selected')->where('school_response', 'accepted')->count(),
                'opted_out_count' => $schSelections->where('school_response', 'opted_out')->count(),
                'total_count' => $schSelections->count(),
            ];
        })->values()->sortBy('school_name')->values();

        $profile = \App\Models\SahodayaProfile::where('tenant_id', $this->sahodaya->id)->first();
        $mailer = \App\Services\Mail\SahodayaMailer::for($this->sahodaya->id);
        $stateSlotMail = $event->notification_settings['state_slot_mail'] ?? [];

        $mailConfig = [
            'is_configured' => $mailer->isConfigured(),
            'from_address' => $profile?->mail_from_address,
            'from_name' => $profile?->mail_from_name ?: $this->sahodaya->name,
            'mailer_type' => $profile?->mail_transport ?: 'default',
            'auto_notify_enabled' => (bool) ($stateSlotMail['auto_notify_enabled'] ?? false),
            'subject_template' => $stateSlotMail['subject_template'] ?? "[{$event->title}] State Kalotsavam Nomination - Confirm Student Slots",
            'message_template' => $stateSlotMail['message_template'] ?? "Dear Principal / Sahodaya In-Charge,\n\nStudents from your school have qualified to represent our Sahodaya at the State Kalotsavam in {$event->title}.\n\nPlease review your qualified student slots on the Sahodaya portal to confirm attendance or opt out if a student is unable to attend, so waiting list candidates can be offered the slot in time.\n\nClick the link below to access your school's slot confirmation page:\n{action_url}\n\nWarm regards,\n{$this->sahodaya->name}",
            'reply_to_email' => $stateSlotMail['reply_to_email'] ?? $profile?->contact_email,
            'last_sent_at' => $stateSlotMail['last_sent_at'] ?? null,
            'last_sent_count' => $stateSlotMail['last_sent_count'] ?? null,
        ];

        return $this->inertia('Sahodaya/Events/StateSlotCollection', [
            'sahodaya' => $this->sahodaya,
            'event' => $event->only([
                'id', 'title', 'event_type', 'state_program_id',
                'state_slot_collection_open', 'state_slot_collection_opened_at',
                'state_slot_collection_approved_at', 'state_slot_collection_approved_by',
            ]),
            'program' => $program->only(['id', 'title']),
            'summary' => $collection->summary($program, $event),
            'schoolsSummary' => $schoolsSummary,
            'mailConfig' => $mailConfig,
            'schoolEntryUrl' => rtrim(\App\Support\TenantDomainSync::publicUrl($this->sahodaya) ?: url('/'), '/').'/state-slot-collection/'.$event->id,
            'actionUrls' => $this->actionUrls($this->sahodaya->id, $event),
        ]);
    }

    public function open(string $tenantId, FestEvent $event, FestStateSlotCollectionService $collection, Request $request)
    {
        $program = $this->context($event);

        $result = $collection->open($program, $event, $request->user());

        return back()->with(
            $result['skipped_ties'] ? 'warning' : 'success',
            "Slot collection opened — {$result['filled']} slot(s) sent to schools for confirmation."
                .($result['skipped_ties']
                    ? ' Left for you to decide first, because a tie has to be broken: '.implode(', ', $result['skipped_ties']).'.'
                    : ''),
        );
    }

    public function approve(string $tenantId, FestEvent $event, FestStateSlotCollectionService $collection, Request $request)
    {
        $program = $this->context($event);

        $result = $collection->approve($program, $event, $request->user());

        return back()->with(
            'success',
            "Approved. {$result['approved_count']} winner(s) confirmed — you can now register with State from the winner sheet."
                .($result['warnings'] ? ' '.implode(' ', $result['warnings']) : ''),
        );
    }

    public function updateSlotQuotas(string $tenantId, FestEvent $event, FestStateSlotCollectionService $collection, Request $request)
    {
        $program = $this->context($event);
        abort_if($event->state_slot_collection_approved_at, 422, 'Cannot change slots after the list has been approved.');

        $data = $request->validate([
            'default_quota' => 'nullable|integer|min:1|max:2',
            'slots' => 'nullable|array',
            'slots.*' => 'nullable|integer|min:1|max:2',
            'sync_now' => 'nullable|boolean',
        ]);

        $nominations = app(\App\Services\State\FestStateNominationService::class);
        $sourceEvents = $nominations->sourceEvents($event, app(\App\Services\Events\FestPartitionService::class));
        $sourceEventIds = collect($sourceEvents)->pluck('id');

        if (! empty($data['default_quota'])) {
            \App\Models\FestEventItem::whereIn('event_id', $sourceEventIds)
                ->whereNotNull('state_program_item_id')
                ->update(['qualify_count' => (int) $data['default_quota']]);
        }

        if (! empty($data['slots'])) {
            foreach ($data['slots'] as $stateItemId => $quota) {
                if ($quota !== null) {
                    \App\Models\FestEventItem::whereIn('event_id', $sourceEventIds)
                        ->where('state_program_item_id', $stateItemId)
                        ->update(['qualify_count' => (int) $quota]);
                }
            }
        }

        $syncMsg = '';
        if ($event->state_slot_collection_open && ! empty($data['sync_now'])) {
            $result = $collection->syncAdditionalSlots($program, $event, $request->user());
            if ($result['filled'] > 0) {
                $syncMsg = " and {$result['filled']} newly added slot(s) sent to schools for confirmation";
            }
        }

        return back()->with('success', "Slot quotas updated successfully{$syncMsg}.");
    }

    public function sendOffer(string $tenantId, FestEvent $event, FestStateSlotCollectionService $collection, Request $request)
    {
        $program = $this->context($event);

        $data = $request->validate([
            'candidate' => 'required|array',
            'candidate.item_id' => 'required|string',
            'candidate.item_code' => 'nullable|string',
            'candidate.mark_id' => 'nullable|string',
            'candidate.participant_id' => 'nullable|string',
            'candidate.registration_id' => 'nullable|string',
            'candidate.source_event_id' => 'nullable|string',
            'candidate.school_id' => 'nullable|string',
            'candidate.student_name' => 'nullable|string',
            'candidate.school_name' => 'nullable|string',
            'candidate.source_position' => 'nullable|integer',
            'candidate.grade' => 'nullable|string',
            'candidate.score' => 'nullable',
            'candidate.partition_key' => 'nullable|string',
            'candidate.direct_entry' => 'nullable|boolean',
        ]);

        $selection = $collection->sendOffer($program, $event, $data['candidate'], $request->user());

        return back()->with('success', "Offer sent to {$selection->student_name} ({$selection->school_name}).");
    }

    public function alterSelection(string $tenantId, FestEvent $event, FestStateNominationSelection $selection, FestStateSlotCollectionService $collection, Request $request)
    {
        $program = $this->context($event);

        $data = $request->validate([
            'action' => 'required|in:reopen,accept,opt_out,revoke',
            'reason' => 'nullable|string|max:1000',
        ]);

        $collection->alterSelection($program, $event, $selection, $data['action'], $data['reason'] ?? null, $request->user());

        $message = match ($data['action']) {
            'reopen' => "Offer reopened for {$selection->student_name}.",
            'accept' => "Offer confirmed on behalf of {$selection->school_name}.",
            'opt_out' => "Recorded opt-out for {$selection->student_name}. Next eligible candidate will be offered.",
            'revoke' => "Offer revoked for {$selection->student_name}.",
        };

        return back()->with('success', $message);
    }

    public function exportZip(string $tenantId, FestEvent $event, FestStateExportService $export, Request $request)
    {
        $program = $this->context($event);
        $confirmedOnly = $request->boolean('confirmed', false);

        $zipPath = $export->generateZip($program, $event, $confirmedOnly);
        $slug = \Illuminate\Support\Str::slug($event->title, '-');
        $filename = "{$slug}-state-nominations-" . now()->format('Ymd-His') . ".zip";

        return response()->download($zipPath, $filename, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-cache, private',
        ])->deleteFileAfterSend(true);
    }

    public function exportExcel(string $tenantId, FestEvent $event, FestStateExportService $export, Request $request)
    {
        $program = $this->context($event);
        $confirmedOnly = $request->boolean('confirmed', false);

        $xlsxBytes = $export->generateExcel($program, $event, $confirmedOnly);
        $slug = \Illuminate\Support\Str::slug($event->title, '-');
        $filename = "{$slug}-state-nominations-" . now()->format('Ymd-His') . ".xlsx";

        return response($xlsxBytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    public function updateMailSettings(string $tenantId, FestEvent $event, Request $request)
    {
        $this->context($event);

        $data = $request->validate([
            'auto_notify_enabled' => 'nullable|boolean',
            'subject_template' => 'required|string|max:255',
            'message_template' => 'required|string|max:5000',
            'reply_to_email' => 'nullable|email|max:255',
        ]);

        $settings = $event->notification_settings ?? [];
        $currentMail = $settings['state_slot_mail'] ?? [];

        $settings['state_slot_mail'] = array_merge($currentMail, [
            'auto_notify_enabled' => (bool) ($data['auto_notify_enabled'] ?? false),
            'subject_template' => $data['subject_template'],
            'message_template' => $data['message_template'],
            'reply_to_email' => $data['reply_to_email'] ?? null,
            'updated_at' => now()->toIso8601String(),
        ]);

        $event->update(['notification_settings' => $settings]);

        return back()->with('success', 'State slot mail settings saved successfully.');
    }

    public function sendEmail(string $tenantId, FestEvent $event, Request $request)
    {
        $program = $this->context($event);

        $data = $request->validate([
            'audience' => 'required|in:pending,all,specific',
            'school_id' => 'nullable|required_if:audience,specific|string',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'include_slot_details' => 'nullable|boolean',
            'test_email' => 'nullable|email|max:255',
        ]);

        $batch = FestStateNominationBatch::where('hub_event_id', $event->id)->first();
        abort_unless($batch, 422, 'No nomination batch found for this event.');

        $selections = $batch->selections()->where('nomination_type', 'primary')->get();
        $schoolEntryUrl = rtrim(\App\Support\TenantDomainSync::publicUrl($this->sahodaya) ?: url('/'), '/').'/state-slot-collection/'.$event->id;

        $mailer = \App\Services\Mail\SahodayaMailer::for($this->sahodaya->id);
        $isMailerConfigured = $mailer->isConfigured();

        // Handle test email send
        if (! empty($data['test_email'])) {
            $testSchoolName = 'Sample School (Test Preview)';
            $testSlots = $selections->take(3)->map(fn ($s) => [
                'student_name' => $s->student_name,
                'item_code' => $s->item_code,
                'item_title' => $s->item_title,
                'class_name' => $s->class_name,
                'source_position' => $s->source_position,
                'school_response' => 'pending',
            ])->all();

            $viewData = [
                'subject' => $data['subject'],
                'sahodayaName' => $this->sahodaya->name,
                'headerEyebrow' => 'State Kalotsavam Nomination (TEST)',
                'headerTitle' => $event->title,
                'headerSubtitle' => 'Test Notification Preview',
                'schoolName' => $testSchoolName,
                'messageBody' => str_replace(['{school_name}', '{event_title}', '{program_title}', '{action_url}', '{pending_count}'], [$testSchoolName, $event->title, $program->title, $schoolEntryUrl, (string) count($testSlots)], $data['message']),
                'slots' => ! empty($data['include_slot_details']) ? $testSlots : [],
                'actionUrl' => $schoolEntryUrl,
            ];

            $this->dispatchEmailToRecipient($mailer, $isMailerConfigured, $data['test_email'], $data['subject'], $viewData);

            return back()->with('success', "Test email sent successfully to {$data['test_email']}.");
        }

        // Target schools determination
        $targetSchoolIds = match ($data['audience']) {
            'pending' => $selections->where('status', 'selected')->where('school_response', 'pending')->pluck('school_id')->filter()->unique()->values(),
            'all' => $selections->pluck('school_id')->filter()->unique()->values(),
            'specific' => collect([$data['school_id']])->filter(),
        };

        if ($targetSchoolIds->isEmpty()) {
            return back()->with('warning', 'No schools match the selected audience filter.');
        }

        $schools = \App\Models\Tenant::whereIn('id', $targetSchoolIds)->get()->keyBy('id');
        $schoolUsers = \App\Models\User::whereIn('tenant_id', $targetSchoolIds)->whereNotNull('email')->get()->groupBy('tenant_id');

        $sentCount = 0;
        $skippedSchools = [];

        foreach ($targetSchoolIds as $schoolId) {
            $sch = $schools->get($schoolId);
            $schSelections = $selections->where('school_id', $schoolId);
            $payload = is_array($sch?->application_payload) ? $sch->application_payload : [];
            $email = $payload['school_email'] ?? $payload['contact_email'] ?? $schoolUsers->get($schoolId)?->first()?->email;

            $schoolName = $sch?->name ?? $schSelections->first()?->school_name ?? "School {$schoolId}";

            if (empty($email)) {
                $skippedSchools[] = $schoolName;
                continue;
            }

            $slotsForEmail = ! empty($data['include_slot_details']) ? $schSelections->map(fn ($s) => [
                'student_name' => $s->student_name,
                'item_code' => $s->item_code,
                'item_title' => $s->item_title,
                'class_name' => $s->class_name,
                'source_position' => $s->source_position,
                'school_response' => $s->school_response,
            ])->values()->all() : [];

            $pendingCount = $schSelections->where('status', 'selected')->where('school_response', 'pending')->count();

            $personalizedSubject = str_replace(['{school_name}', '{event_title}', '{program_title}'], [$schoolName, $event->title, $program->title], $data['subject']);
            $personalizedBody = str_replace(
                ['{school_name}', '{event_title}', '{program_title}', '{action_url}', '{pending_count}'],
                [$schoolName, $event->title, $program->title, $schoolEntryUrl, (string) $pendingCount],
                $data['message']
            );

            $viewData = [
                'subject' => $personalizedSubject,
                'sahodayaName' => $this->sahodaya->name,
                'headerEyebrow' => 'State Kalotsavam Nomination',
                'headerTitle' => $event->title,
                'headerSubtitle' => 'Student Slot Confirmation Notice',
                'schoolName' => $schoolName,
                'messageBody' => $personalizedBody,
                'slots' => $slotsForEmail,
                'actionUrl' => $schoolEntryUrl,
            ];

            try {
                $this->dispatchEmailToRecipient($mailer, $isMailerConfigured, $email, $personalizedSubject, $viewData);
                $sentCount++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed sending state slot email to {$email}: " . $e->getMessage());
                $skippedSchools[] = "{$schoolName} (send error)";
            }
        }

        // Update event mail history
        $settings = $event->notification_settings ?? [];
        $currentMail = $settings['state_slot_mail'] ?? [];
        $settings['state_slot_mail'] = array_merge($currentMail, [
            'last_sent_at' => now()->toIso8601String(),
            'last_sent_count' => $sentCount,
            'last_subject' => $data['subject'],
        ]);
        $event->update(['notification_settings' => $settings]);

        $msg = "Notification email successfully sent to {$sentCount} school(s).";
        if (! empty($skippedSchools)) {
            $msg .= ' ' . count($skippedSchools) . ' school(s) were skipped (no email or send error): ' . implode(', ', array_slice($skippedSchools, 0, 5)) . (count($skippedSchools) > 5 ? '...' : '.');
        }

        return back()->with('success', $msg);
    }

    private function dispatchEmailToRecipient(\App\Services\Mail\SahodayaMailer $mailer, bool $isConfigured, string $to, string $subject, array $viewData): void
    {
        if ($isConfigured) {
            $mailer->sendView($to, $subject, 'emails.fest.state-slot-notification', $viewData);
        } else {
            \Illuminate\Support\Facades\Mail::send('emails.fest.state-slot-notification', $viewData, function ($msg) use ($to, $subject) {
                $msg->to($to)->subject($subject);
            });
        }
    }

    private function context(FestEvent $event): FestStateProgram
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if(! $event->state_program_id, 422, 'This event is not linked to a State program.');
        abort_if($event->parent_event_id, 422, 'Only the hub event collects State slot responses.');

        return FestStateProgram::findOrFail($event->state_program_id);
    }

    /** @return array<string, string> */
    private function actionUrls(string $tenantId, FestEvent $event): array
    {
        $base = "/sahodaya-admin/{$tenantId}/events/{$event->id}/state-slot-collection";

        return [
            'open' => "{$base}/open",
            'approve' => "{$base}/approve",
            'updateSlots' => "{$base}/update-slots",
            'sendOffer' => "{$base}/offer",
            'alterSelection' => "{$base}/selections",
            'exportZip' => "{$base}/export-zip",
            'exportExcel' => "{$base}/export-excel",
            'sendEmail' => "{$base}/send-email",
            'mailSettings' => "{$base}/mail-settings",
            'overview' => "/sahodaya-admin/{$tenantId}/state-slot-collection",
            'winnerSheet' => "/sahodaya-admin/{$tenantId}/events/{$event->id}/state-winners",
        ];
    }
}
