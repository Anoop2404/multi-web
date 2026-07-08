<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsItemHeadReportContext;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestChestNumberService;
use App\Services\Events\FestHeadItemNavigationService;
use App\Services\Events\FestNumberingService;
use App\Support\FestPageActivity;
use Illuminate\Http\Request;

class FestChestNumberController extends SahodayaAdminController
{
    use BuildsItemHeadReportContext;

    public function index(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $navService = app(FestHeadItemNavigationService::class);
        $nav = $navService->navigationForEvent($event);

        $headId = $this->resolveHeadQueryParam($request->query('head_id'));
        $itemId = $request->integer('item_id') ?: null;
        $includePending = $request->boolean('include_pending');
        $participants = [];
        $greenRoom = [];
        $selectedItem = null;

        if ($itemId) {
            $selectedItem = $navService->findItemInGroups($nav['headItemGroups'], $itemId);
            abort_unless($selectedItem, 404);
            $participants = $this->participantRows($event, $itemId, $includePending);
            $greenRoom = $this->greenRoomRows($event, $itemId);
        }

        $selectedHeadId = match (true) {
            $headId === 0 => 'other',
            $headId !== null => $headId,
            default => null,
        };

        return $this->inertia('Sahodaya/Events/ChestNumbers', $this->withEventActivity($event, FestPageActivity::CHEST_NUMBERS, array_merge($nav, [
            'event'          => $event->only('id', 'title', 'status', 'event_type', 'chest_reveal_mode', 'results_published'),
            'selectedHeadId' => $selectedHeadId,
            'selectedItemId' => $itemId,
            'selectedItem'   => $selectedItem,
            'participants'   => $participants,
            'greenRoom'      => $greenRoom,
            'includePending' => $includePending,
            'view'           => $request->query('view') === 'green-room' ? 'green-room' : null,
        ])));
    }

    public function greenRoom(Request $request, string $tenantId, FestEvent $event)
    {
        $query = array_filter([
            'head_id' => $request->query('head_id'),
            'item_id' => $request->query('item_id'),
            'view'    => 'green-room',
        ]);

        return redirect()->route('sahodaya.events.chest-numbers.index', [
            'tenantId' => $tenantId,
            'event'    => $event->id,
            ...$query,
        ]);
    }

    public function generate(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'item_id' => 'required|integer|exists:fest_event_items,id',
        ]);

        $item = FestEventItem::where('event_id', $event->id)->findOrFail($data['item_id']);
        $count = app(FestNumberingService::class)->assignMissingChestNumbers($event, $item);

        $audit->festEvent($event, FestPageActivity::CHEST_NUMBERS, 'fest.chest_numbers.generated', "Assigned {$count} chest number(s) for {$item->title}", [
            'count'   => $count,
            'item_id' => $item->id,
        ]);

        return back()->with('success', "Assigned {$count} chest number(s) for {$item->title}.");
    }

    public function assignMissing(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'item_id' => 'required|integer|exists:fest_event_items,id',
        ]);

        $item = FestEventItem::where('event_id', $event->id)->findOrFail($data['item_id']);
        $chestCount = app(FestNumberingService::class)->assignMissingChestNumbers($event, $item);

        $audit->festEvent($event, FestPageActivity::CHEST_NUMBERS, 'fest.chest_numbers.assigned_missing', "Assigned {$chestCount} missing chest number(s) for {$item->title}", [
            'count'   => $chestCount,
            'item_id' => $item->id,
        ]);

        return back()->with('success', "Assigned {$chestCount} missing chest number(s) for {$item->title}.");
    }

    public function assignItemRegIds(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'item_id' => 'required|integer|exists:fest_event_items,id',
        ]);

        $item = FestEventItem::where('event_id', $event->id)->findOrFail($data['item_id']);
        $count = app(FestNumberingService::class)->assignMissingItemRegNumbers($event, $item);

        $audit->festEvent($event, FestPageActivity::CHEST_NUMBERS, 'fest.item_reg_ids.assigned', "Assigned {$count} item registration ID(s) for {$item->title}", [
            'count'   => $count,
            'item_id' => $item->id,
        ]);

        return back()->with('success', "Assigned {$count} item registration ID(s) for {$item->title}.");
    }

    public function clearChest(string $tenantId, FestEvent $event, FestParticipant $participant, FestChestNumberService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($participant->registration->event_id !== $event->id, 403);

        $service->clearChest($participant);

        $audit->festEvent($event, FestPageActivity::CHEST_NUMBERS, 'fest.chest_number.cleared', 'Chest number cleared', [
            'participant_id' => $participant->id,
        ]);

        return back()->with('success', 'Chest number cleared.');
    }

    public function revealChest(string $tenantId, FestEvent $event, FestParticipant $participant, FestChestNumberService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($participant->registration->event_id !== $event->id, 403);

        $service->revealAtStageEntry($participant);

        $audit->festEvent($event, FestPageActivity::CHEST_NUMBERS, 'fest.chest_number.revealed', 'Chest number revealed', [
            'participant_id' => $participant->id,
        ]);

        $participant->loadMissing('registration.school', 'student', 'teacher');
        $schoolId = $participant->registration?->school_id;
        if ($schoolId) {
            app(\App\Services\Events\FestEventNotifier::class)->notifySchoolForChestReveal(
                $event,
                $schoolId,
                $participant->student?->name ?? $participant->teacher?->name ?? 'Participant',
            );
        }

        return back()->with('success', 'Chest number revealed.');
    }

    public function print(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $itemId = $request->integer('item_id') ?: null;
        $item = null;

        if ($itemId) {
            $item = FestEventItem::where('event_id', $event->id)->findOrFail($itemId);
        }

        $rows = $this->chestNumberRows($event, $itemId);

        return view('fest.chest-numbers-print', [
            'event' => $event,
            'item'  => $item,
            'rows'  => $rows,
        ]);
    }

    public function cards(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $itemId = $request->integer('item_id') ?: null;
        $item = $itemId ? FestEventItem::where('event_id', $event->id)->findOrFail($itemId) : null;

        return view('fest.chest-cards-print', [
            'event' => $event,
            'item'  => $item,
            'rows'  => $this->chestNumberRows($event, $itemId),
        ]);
    }

    public function csv(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $rows = $this->chestNumberRows($event, $request->integer('item_id') ?: null);
        $filename = str($event->title)->slug()->limit(40)->toString().'-chest-numbers.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Chest No', 'Fest ID', 'Item Reg No', 'Participant', 'Item', 'School']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['chest_no'],
                    $row['fest_id'],
                    $row['item_reg'],
                    $row['name'],
                    $row['item'],
                    $row['school'],
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return list<array<string, mixed>> */
    private function participantRows(FestEvent $event, int $itemId, bool $includePending = false): array
    {
        return FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('item_id', $itemId)
            ->when($includePending, fn ($q2) => $q2->active(), fn ($q2) => $q2->where('status', 'approved')))
            ->with(['registration.school', 'registration', 'student', 'teacher', 'group'])
            ->get()
            ->sortBy(fn ($p) => [$p->chest_no ?? 99999, $p->id])
            ->values()
            ->map(function (FestParticipant $p) {
                return [
                    'id'                => $p->id,
                    'chest_no'          => $p->chest_no,
                    'chest_revealed_at' => $p->chest_revealed_at,
                    'fest_id'           => $p->level_registration_number,
                    'item_reg'          => $p->item_registration_number,
                    'name'              => $p->student?->name ?? $p->teacher?->name,
                    'school'            => $p->registration->school?->name ?? Tenant::find($p->registration->school_id)?->name,
                    'group'             => $p->group?->team_name,
                    'reg_status'        => $p->registration->status,
                ];
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function greenRoomRows(FestEvent $event, int $itemId): array
    {
        return FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('item_id', $itemId)
            ->where('status', 'approved')
            ->whereHas('item', fn ($i) => $i->where('stage_type', 'on_stage')))
            ->whereNull('chest_revealed_at')
            ->with(['registration.school', 'student', 'teacher'])
            ->orderBy('chest_no')
            ->get()
            ->map(function (FestParticipant $p) {
                return [
                    'id'       => $p->id,
                    'chest_no' => $p->chest_no,
                    'fest_id'  => $p->level_registration_number,
                    'name'     => $p->student?->name ?? $p->teacher?->name,
                    'school'   => $p->registration->school?->name ?? Tenant::find($p->registration->school_id)?->name,
                ];
            })
            ->all();
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function chestNumberRows(FestEvent $event, ?int $itemId = null)
    {
        return FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved')
            ->when($itemId, fn ($q2) => $q2->where('item_id', $itemId)))
            ->whereNotNull('chest_no')
            ->with(['registration.item', 'registration.school', 'student', 'teacher'])
            ->get()
            ->sortBy(fn ($p) => [$p->registration->item_id ?? 0, $p->chest_no ?? 9999])
            ->values()
            ->map(function (FestParticipant $p) {
                return [
                    'chest_no' => $p->chest_no,
                    'fest_id'  => $p->level_registration_number,
                    'item_reg' => $p->item_registration_number,
                    'name'     => $p->student?->name ?? $p->teacher?->name,
                    'item'     => $p->registration->item?->title,
                    'school'   => $p->registration?->school?->name ?? Tenant::find($p->registration->school_id)?->name,
                ];
            });
    }
}
