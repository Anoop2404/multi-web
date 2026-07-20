<?php

namespace App\Services\Events;

use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use App\Support\ExcelExport;
use Illuminate\Support\Facades\Schema;
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

    /**
     * Every approved participant, not just the ones already marked — previously this
     * only listed rows already present in fest_attendance, so anyone not yet marked
     * (the whole point of reviewing this report before/during the event) was silently
     * missing rather than showing as blank/"Not marked".
     */
    public function attendance(FestEvent $event): StreamedResponse
    {
        $schools = $this->schoolNames($event);

        $attendance = FestAttendance::where('event_id', $event->id)
            ->get()
            ->keyBy(fn (FestAttendance $a) => $a->item_id.'-'.$a->participant_id);

        $rows = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->with(['student', 'teacher', 'registration.item', 'registration.school'])
            ->get()
            ->sortBy(fn (FestParticipant $p) => $p->registration?->item?->title)
            ->map(function (FestParticipant $p) use ($attendance, $schools) {
                $a = $attendance->get($p->registration?->item_id.'-'.$p->id);

                return [
                    $p->registration?->item?->title ?? '',
                    $p->student?->name ?? $p->teacher?->name ?? '',
                    $schools[$p->registration?->school_id] ?? $p->registration?->school_id ?? '',
                    $p->chest_no ?? '',
                    $a?->status ?? 'Not marked',
                    $a?->marked_at?->format('Y-m-d H:i') ?? '',
                ];
            })
            ->values();

        return ExcelExport::download(
            $this->filename($event, 'attendance'),
            ['Item', 'Participant', 'School', 'Chest No', 'Status', 'Marked At'],
            $rows,
        );
    }

    public function fees(FestEvent $event): StreamedResponse
    {
        $schoolIds = FestSchoolEventFee::where('event_id', $event->id)->pluck('school_id')->unique();
        $schools = Tenant::whereIn('id', $schoolIds)->pluck('name', 'id');
        $hasCompositeColumns = Schema::hasColumn('fest_school_event_fees', 'student_registration_fee');

        $rows = FestSchoolEventFee::where('event_id', $event->id)
            ->with('feeReceipt')
            ->orderBy('school_id')
            ->get()
            ->map(function (FestSchoolEventFee $fee) use ($schools, $hasCompositeColumns) {
                $row = [
                    $schools[$fee->school_id] ?? $fee->school_id,
                    $fee->school_registration_fee,
                ];

                if ($hasCompositeColumns) {
                    $row[] = $fee->student_registration_fee ?? 0;
                    $row[] = $fee->extra_item_fee ?? 0;
                }

                return array_merge($row, [
                    $fee->participation_item_count,
                    $fee->participation_fee,
                    $fee->total_due,
                    $fee->feeReceipt?->amount ?? '',
                    $fee->status,
                    $fee->feeReceipt?->transaction_ref ?? '',
                    $fee->feeReceipt?->payment_date?->format('Y-m-d') ?? '',
                    $fee->feeReceipt?->receipt_number ?? '',
                ]);
            });

        $headers = ['School', 'School Reg'];
        if ($hasCompositeColumns) {
            $headers = array_merge($headers, ['Student Reg', 'Extra Items']);
        }
        $headers = array_merge($headers, [
            'Student/Item Count', 'Participation Fee', 'Total Due', 'Paid', 'Status', 'Txn Ref', 'Payment Date', 'Receipt No',
        ]);

        return ExcelExport::download(
            $this->filename($event, 'fees'),
            $headers,
            $rows,
        );
    }

    public function feeBreakdown(FestEvent $event): StreamedResponse
    {
        if (! Schema::hasTable('fest_school_event_fee_lines')) {
            return $this->fees($event);
        }

        $schoolIds = FestSchoolEventFee::where('event_id', $event->id)->pluck('school_id')->unique();
        $schools = Tenant::whereIn('id', $schoolIds)->pluck('name', 'id');

        $rows = FestSchoolEventFee::where('event_id', $event->id)
            ->with('lines')
            ->orderBy('school_id')
            ->get()
            ->flatMap(function (FestSchoolEventFee $fee) use ($schools) {
                if ($fee->lines->isEmpty()) {
                    return [[
                        $schools[$fee->school_id] ?? $fee->school_id,
                        'summary',
                        'Total',
                        1,
                        $fee->total_due,
                        $fee->total_due,
                        $fee->status,
                    ]];
                }

                return $fee->lines->map(fn ($line) => [
                    $schools[$fee->school_id] ?? $fee->school_id,
                    $line->line_type,
                    $line->label,
                    $line->quantity,
                    $line->unit_amount,
                    $line->amount,
                    $fee->status,
                ]);
            });

        return ExcelExport::download(
            $this->filename($event, 'fee-breakdown'),
            ['School', 'Line type', 'Label', 'Qty', 'Unit', 'Amount', 'Status'],
            $rows,
        );
    }

    public function studentEventRegistrations(FestEvent $event): StreamedResponse
    {
        $rows = \App\Models\FestLevelRegistration::where('event_id', $event->id)
            ->with(['student:id,name,reg_no', 'school:id,name'])
            ->orderBy('registration_number')
            ->get()
            ->map(fn ($r) => [
                $r->school?->name ?? $r->school_id,
                $r->student?->reg_no ?? '',
                $r->student?->name ?? '',
                $r->registration_number,
                $r->status,
                $r->registered_at?->format('Y-m-d H:i') ?? '',
            ]);

        return ExcelExport::download(
            $this->filename($event, 'student-event-registrations'),
            ['School', 'Reg no', 'Student', 'Event reg ID', 'Status', 'Registered at'],
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
