<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\FestStage;
use App\Models\FestVenue;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestItemScheduleService;
use App\Services\Events\FestScheduleConflictService;
use App\Services\Events\FestScheduleImportService;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestParticipantLookupService;
use Illuminate\Http\Request;

class FestScheduleController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load('items');

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->with(['registration.item', 'student', 'teacher', 'group'])
            ->get();

        $schedules = FestSchedule::where('event_id', $event->id)
            ->with(['item', 'participant.student', 'participant.teacher', 'festStage.venue'])
            ->orderBy('scheduled_at')
            ->orderBy('sort_order')
            ->get();

        $conflictService = new FestScheduleConflictService($event);
        $clashes = $conflictService->detectAll();
        $stageConflicts = $conflictService->detectStageConflicts();

        return $this->inertia('Sahodaya/Events/Schedule', $this->withEventActivity($event, FestPageActivity::SCHEDULE, [
            'event'        => $event,
            'participants' => $participants,
            'schedules'    => $schedules,
            'stages'       => FestStage::where('event_id', $event->id)
                ->with('venue:id,name')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'clashCount'   => count($clashes) + count($stageConflicts),
            'clashes'      => array_slice($clashes, 0, 25),
            'stageConflicts' => array_slice($stageConflicts, 0, 25),
        ]));
    }

    public function store(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'item_id'        => 'required|exists:fest_event_items,id',
            'participant_id' => 'nullable|exists:fest_participants,id',
            'scheduled_at'   => 'nullable|date',
            'stage_id'       => 'nullable|exists:fest_stages,id',
            'stage'          => 'nullable|string|max:100',
            'sort_order'     => 'nullable|integer|min:0',
        ]);

        if (! empty($data['stage_id'])) {
            $stage = FestStage::where('event_id', $event->id)->findOrFail($data['stage_id']);
            $data['stage'] = $stage->name;
        }

        $data['event_id'] = $event->id;
        $data['sort_order'] = $data['sort_order'] ?? (FestSchedule::where('event_id', $event->id)->max('sort_order') ?? 0) + 1;

        FestSchedule::updateOrCreate(
            [
                'item_id'        => $data['item_id'],
                'participant_id' => $data['participant_id'] ?? null,
            ],
            $data
        );

        $audit->festEvent($event, FestPageActivity::SCHEDULE, 'fest.schedule.saved', 'Schedule slot saved', [
            'item_id'        => $data['item_id'],
            'participant_id' => $data['participant_id'] ?? null,
        ]);

        $conflictService = new FestScheduleConflictService($event);
        $clashes = $conflictService->detectAll();
        $stageConflicts = $conflictService->detectStageConflicts();
        if ($clashes !== [] || $stageConflicts !== []) {
            $total = count($clashes) + count($stageConflicts);

            return back()
                ->with('success', 'Schedule saved.')
                ->with('warning', "{$total} schedule clash(es) detected. Resolve before publishing.");
        }

        return back()->with('success', 'Schedule saved.');
    }

    public function autoGenerate(string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->with('registration.item')
            ->get()
            ->sortBy(fn ($p) => [$p->registration->item_id, $p->chest_no ?? 9999, $p->id]);

        $order = 1;
        foreach ($participants as $participant) {
            FestSchedule::updateOrCreate(
                [
                    'item_id'        => $participant->registration->item_id,
                    'participant_id' => $participant->id,
                ],
                [
                    'event_id'   => $event->id,
                    'sort_order' => $order++,
                ]
            );
        }

        $audit->festEvent($event, FestPageActivity::SCHEDULE, 'fest.schedule.auto_generated', 'Performance order auto-generated from chest numbers', [
            'count' => $order - 1,
        ]);

        return back()->with('success', 'Performance order generated from chest numbers.');
    }

    public function publishSchedule(string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        EventLifecycleGate::allowSchedulePublish($event);

        abort_unless(
            FestSchedule::where('event_id', $event->id)->exists(),
            422,
            'Build the schedule before publishing.'
        );

        $conflictService = new FestScheduleConflictService($event);
        $clashes = $conflictService->detectAll();
        $stageConflicts = $conflictService->detectStageConflicts();
        abort_if(
            $clashes !== [] || $stageConflicts !== [],
            422,
            (count($clashes) + count($stageConflicts)).' schedule clash(es) must be resolved before publishing.'
        );

        $event->update(['schedule_published' => true]);

        $audit->festEvent($event, FestPageActivity::SCHEDULE, 'fest.schedule.published', 'Schedule published to public portal');

        app(\App\Services\Events\FestEventNotifier::class)->schedulePublished($event);

        return back()->with('success', 'Schedule published to the public fest portal.');
    }

    public function unpublishSchedule(string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->update(['schedule_published' => false]);

        $audit->festEvent($event, FestPageActivity::SCHEDULE, 'fest.schedule.unpublished', 'Schedule hidden from public portal');

        return back()->with('success', 'Schedule hidden from public portal.');
    }

    public function importTemplate(string $tenantId, FestEvent $event, FestParticipantLookupService $lookup)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $rows = $lookup->approvedRowsForTemplate($event);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['item_id', 'item_title', 'reg_no', 'chest_no', 'name', 'scheduled_at', 'stage', 'sort_order']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['item_id'],
                    $row['item_title'],
                    $row['reg_no'],
                    $row['chest_no'],
                    $row['name'],
                    '', '', '',
                ]);
            }
            fclose($out);
        }, "fest-schedule-{$event->id}-template.csv", ['Content-Type' => 'text/csv']);
    }

    public function importStore(Request $request, string $tenantId, FestEvent $event, FestScheduleImportService $importService, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $result = $importService->importFromCsv($event, $request->file('file')->getRealPath());

        $audit->festEvent($event, FestPageActivity::SCHEDULE, 'fest.schedule.imported', "Imported {$result['imported']} schedule row(s)", [
            'imported' => $result['imported'],
        ]);

        $message = "Imported {$result['imported']} schedule row(s).";
        if ($result['errors'] !== []) {
            $message .= ' '.count($result['errors']).' row(s) skipped.';
        }

        return back()
            ->with('success', $message)
            ->with('importErrors', $result['errors']);
    }

    public function destroy(string $tenantId, FestEvent $event, FestSchedule $schedule, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($schedule->event_id !== $event->id, 404);

        $schedule->delete();

        $audit->festEvent($event, FestPageActivity::SCHEDULE, 'fest.schedule.deleted', 'Schedule slot removed', [
            'schedule_id' => $schedule->id,
        ]);

        return back()->with('success', 'Schedule slot removed.');
    }

    public function reorder(string $tenantId, FestEvent $event, FestSchedule $schedule, Request $request, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($schedule->event_id !== $event->id, 404);

        $data = $request->validate(['direction' => 'required|in:up,down']);

        $ordered = FestSchedule::where('event_id', $event->id)
            ->orderBy('scheduled_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $index = $ordered->search(fn (FestSchedule $row) => $row->id === $schedule->id);
        if ($index === false) {
            return back();
        }

        $swapIndex = $data['direction'] === 'up' ? $index - 1 : $index + 1;
        if ($swapIndex < 0 || $swapIndex >= $ordered->count()) {
            return back();
        }

        $other = $ordered[$swapIndex];
        $currentOrder = $schedule->sort_order;
        $schedule->update(['sort_order' => $other->sort_order]);
        $other->update(['sort_order' => $currentOrder]);

        $audit->festEvent($event, FestPageActivity::SCHEDULE, 'fest.schedule.reordered', 'Schedule order updated', [
            'schedule_id' => $schedule->id,
            'direction'   => $data['direction'],
        ]);

        return back()->with('success', 'Schedule order updated.');
    }

    public function itemsIndex(string $tenantId, FestEvent $event, FestItemScheduleService $itemSchedule)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load('items');

        return $this->inertia('Sahodaya/Events/ItemSchedule', $this->withEventActivity($event, FestPageActivity::SCHEDULE, [
            'event'     => $event,
            'rows'      => $itemSchedule->rowsForEvent($event),
            'summary'   => $itemSchedule->summary($event),
            'stages'    => FestStage::where('event_id', $event->id)
                ->with('venue:id,name')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'venues'    => FestVenue::where('event_id', $event->id)->orderBy('name')->get(['id', 'name', 'location']),
            'ageGroups' => $event->items->pluck('age_group')->filter()->unique()->values(),
        ]));
    }

    public function bulkStoreItems(Request $request, string $tenantId, FestEvent $event, FestItemScheduleService $itemSchedule, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'rows'                 => 'required|array',
            'rows.*.item_id'       => 'required|integer',
            'rows.*.scheduled_at'  => 'nullable|string',
            'rows.*.scheduled_date'=> 'nullable|date_format:Y-m-d',
            'rows.*.scheduled_time'=> 'nullable|string|max:10',
            'rows.*.stage_id'      => 'nullable|integer',
            'rows.*.stage'         => 'nullable|string|max:100',
            'rows.*.sort_order'    => 'nullable|integer|min:0',
        ]);

        $saved = $itemSchedule->bulkSave($event, $data['rows']);

        $audit->festEvent($event, FestPageActivity::SCHEDULE, 'fest.item_schedule.saved', "Saved schedule for {$saved} item(s)", [
            'count' => $saved,
        ]);

        return back()->with('success', "Schedule saved for {$saved} item(s).");
    }

    public function itemImportTemplate(string $tenantId, FestEvent $event, FestItemScheduleService $itemSchedule)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return response()->streamDownload(function () use ($itemSchedule, $event) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['item_id', 'item_title', 'scheduled_date', 'scheduled_time', 'stage', 'sort_order']);
            foreach ($itemSchedule->rowsForEvent($event) as $row) {
                fputcsv($out, [
                    $row['item_id'],
                    $row['title'],
                    $row['scheduled_date'] ?? '',
                    $row['scheduled_time'] ?? '',
                    $row['stage'] ?? '',
                    $row['sort_order'] ?? '',
                ]);
            }
            fclose($out);
        }, "fest-item-schedule-{$event->id}-template.csv", ['Content-Type' => 'text/csv']);
    }

    public function itemImportStore(Request $request, string $tenantId, FestEvent $event, FestItemScheduleService $itemSchedule, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $result = $itemSchedule->importFromCsv($event, $request->file('file')->getRealPath());

        $audit->festEvent($event, FestPageActivity::SCHEDULE, 'fest.item_schedule.imported', "Imported {$result['imported']} item schedule row(s)", [
            'imported' => $result['imported'],
        ]);

        $message = "Imported {$result['imported']} item schedule row(s).";
        if ($result['errors'] !== []) {
            $message .= ' '.count($result['errors']).' row(s) skipped.';
        }

        return back()
            ->with('success', $message)
            ->with('importErrors', $result['errors']);
    }
}
