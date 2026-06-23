<?php

namespace App\Services\Events;

use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Support\ExcelExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FestExportService
{
    public function registrations(FestEvent $event): StreamedResponse
    {
        $schools = $this->schoolNames($event);

        $rows = FestRegistration::where('event_id', $event->id)
            ->with(['item', 'participants.student', 'participants.teacher', 'feeReceipt'])
            ->orderBy('school_id')
            ->get()
            ->flatMap(function (FestRegistration $reg) use ($schools) {
                return $reg->participants->map(fn ($p) => [
                    $schools[$reg->school_id] ?? $reg->school_id,
                    $reg->item?->title ?? '',
                    $reg->status,
                    $p->chest_no ?? '',
                    $p->student?->name ?? $p->teacher?->name ?? '',
                    $reg->feeReceipt?->amount ?? '',
                    $reg->feeReceipt?->status ?? '',
                ]);
            });

        return ExcelExport::download(
            $this->filename($event, 'registrations'),
            ['School', 'Item', 'Status', 'Chest No', 'Participant', 'Fee Amount', 'Fee Status'],
            $rows,
        );
    }

    public function results(FestEvent $event): StreamedResponse
    {
        $schools = $this->schoolNames($event);

        $rows = FestMark::where('fest_marks.event_id', $event->id)
            ->with(['participant.student', 'participant.teacher', 'participant.registration.item', 'item'])
            ->join('fest_event_items', 'fest_marks.item_id', '=', 'fest_event_items.id')
            ->orderBy('fest_event_items.title')
            ->orderBy('fest_marks.position')
            ->select('fest_marks.*')
            ->get()
            ->map(fn (FestMark $m) => [
                $m->item?->title ?? '',
                $schools[$m->participant?->registration?->school_id ?? ''] ?? '',
                $m->participant?->student?->name ?? $m->participant?->teacher?->name ?? '',
                $m->participant?->chest_no ?? '',
                $m->position ?? '',
                $m->grade ?? '',
                $m->score ?? '',
            ]);

        return ExcelExport::download(
            $this->filename($event, 'results'),
            ['Item', 'School', 'Participant', 'Chest No', 'Position', 'Grade', 'Score'],
            $rows,
        );
    }

    public function attendance(FestEvent $event): StreamedResponse
    {
        $event->load('items');
        $itemTitles = $event->items->pluck('title', 'id');

        $rows = FestAttendance::where('fest_attendance.event_id', $event->id)
            ->with(['participant.student', 'participant.teacher'])
            ->join('fest_event_items', 'fest_attendance.item_id', '=', 'fest_event_items.id')
            ->orderBy('fest_event_items.title')
            ->select('fest_attendance.*')
            ->get()
            ->map(fn (FestAttendance $a) => [
                $itemTitles[$a->item_id] ?? '',
                $a->participant?->student?->name ?? $a->participant?->teacher?->name ?? '',
                $a->participant?->chest_no ?? '',
                $a->status,
                $a->marked_at?->format('Y-m-d H:i') ?? '',
            ]);

        return ExcelExport::download(
            $this->filename($event, 'attendance'),
            ['Item', 'Participant', 'Chest No', 'Status', 'Marked At'],
            $rows,
        );
    }

    public function fees(FestEvent $event): StreamedResponse
    {
        $schools = $this->schoolNames($event);
        $feeService = app(FestRegistrationFeeService::class);

        $rows = FestRegistration::where('event_id', $event->id)
            ->with(['item', 'feeReceipt'])
            ->whereNotNull('fee_receipt_id')
            ->get()
            ->map(fn (FestRegistration $r) => [
                $schools[$r->school_id] ?? $r->school_id,
                $r->item?->title ?? '',
                $feeService->amountDue($event, $r),
                $r->feeReceipt?->amount ?? '',
                $r->feeReceipt?->status ?? '',
                $r->feeReceipt?->transaction_ref ?? '',
                $r->feeReceipt?->payment_date?->format('Y-m-d') ?? '',
            ]);

        return ExcelExport::download(
            $this->filename($event, 'fees'),
            ['School', 'Item', 'Due', 'Paid', 'Status', 'Transaction Ref', 'Payment Date'],
            $rows,
        );
    }

    private function schoolNames(FestEvent $event): array
    {
        $ids = FestRegistration::where('event_id', $event->id)->pluck('school_id')->unique();

        return Tenant::whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    private function filename(FestEvent $event, string $type): string
    {
        $slug = str($event->title)->slug()->limit(40);

        return "{$slug}-{$type}";
    }
}
