<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FestRegistrationRegisterService
{
    public function __construct(
        private FestSchoolEventFeeService $feeService,
        private FestItemFeeResolver $itemFeeResolver,
    ) {}

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     school_summaries: list<array<string, mixed>>,
     *     totals: array<string, mixed>
     * }
     */
    public function build(FestEvent $event, ?string $schoolId = null): array
    {
        $schedule = $this->feeService->resolveSchedule($event);
        $feeRequired = $this->feeService->feeRequired($event);

        $schoolFees = FestSchoolEventFee::where('event_id', $event->id)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->with('feeReceipt')
            ->get()
            ->keyBy('school_id');

        $registrations = FestRegistration::where('event_id', $event->id)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->whereNotIn('status', ['withdrawn', 'rejected'])
            ->with([
                'school:id,name',
                'item:id,title,participant_type,class_group,age_group,fee_amount,head_id',
                'item.head:id,name',
                'participants.student:id,name,reg_no',
                'participants.teacher:id,name,reg_no',
            ])
            ->orderBy('school_id')
            ->get();

        $rows = [];
        $itemFeeCache = [];

        foreach ($registrations as $registration) {
            $schoolFee = $schoolFees->get($registration->school_id);
            $itemKey = (string) ($registration->item_id ?? 'none');
            if (! isset($itemFeeCache[$itemKey])) {
                $itemFeeCache[$itemKey] = $feeRequired
                    ? $this->itemFeeResolver->amountForItem($registration->item, $schedule, $event)
                    : 0.0;
            }

            foreach ($registration->participants ?? [] as $participant) {
                $rows[] = $this->rowFromParticipant(
                    $event,
                    $registration,
                    $participant,
                    $itemFeeCache[$itemKey],
                    $schoolFee,
                    $feeRequired,
                );
            }
        }

        usort($rows, function (array $a, array $b) {
            return [$a['school_name'], $a['participant_name'], $a['item_title']]
                <=> [$b['school_name'], $b['participant_name'], $b['item_title']];
        });

        $schoolSummaries = $this->schoolSummaries($event, $schoolFees, $feeRequired, $schoolId);

        return [
            'rows'             => $rows,
            'school_summaries' => $schoolSummaries,
            'totals'           => [
                'participants'   => count($rows),
                'registrations'  => $registrations->count(),
                'schools'        => $schoolSummaries->count(),
                'total_due'      => round((float) $schoolSummaries->sum('total_due'), 2),
                'total_collected'=> round((float) $schoolSummaries->where('fee_status', 'approved')->sum('total_due'), 2),
                'fee_required'   => $feeRequired,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function schools(FestEvent $event): array
    {
        return Tenant::where('parent_id', $event->tenant_id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function exportCsv(FestEvent $event, ?string $schoolId = null): StreamedResponse
    {
        $data = $this->build($event, $schoolId);
        $slug = str($event->title)->slug('-');
        $filename = $schoolId
            ? "{$slug}-registration-register-{$schoolId}.csv"
            : "{$slug}-registration-register.csv";

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'School', 'Student', 'School reg no', 'Fest ID', 'Item reg no', 'Item', 'Reg status', 'Role',
                'Chest no', 'Item fee', 'School total due', 'Fee status',
            ]);
            foreach ($data['rows'] as $row) {
                fputcsv($out, [
                    $row['school_name'],
                    $row['participant_name'],
                    $row['participant_reg_no'],
                    $row['level_reg'],
                    $row['item_reg'],
                    $row['item_title'],
                    $row['registration_status'],
                    $row['participant_role'],
                    $row['chest_no'],
                    $row['item_fee'],
                    $row['school_total_due'],
                    $row['school_fee_status'],
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function schoolSummaries(FestEvent $event, $schoolFees, bool $feeRequired, ?string $schoolId)
    {
        $schoolIds = FestRegistration::where('event_id', $event->id)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->whereNotIn('status', ['withdrawn', 'rejected'])
            ->distinct()
            ->pluck('school_id');

        return $schoolIds->map(function (string $sid) use ($event, $schoolFees, $feeRequired) {
            $fee = $schoolFees->get($sid);
            if (! $fee && $feeRequired) {
                $fee = $this->feeService->recalculate($event, $sid);
            }

            $school = Tenant::find($sid);

            return [
                'school_id'    => $sid,
                'school_name'  => $school?->name ?? $sid,
                'item_count'   => FestRegistration::where('event_id', $event->id)
                    ->where('school_id', $sid)
                    ->whereIn('status', ['submitted', 'approved'])
                    ->count(),
                'total_due'    => (float) ($fee?->total_due ?? 0),
                'fee_status'   => $fee?->status ?? ($feeRequired ? 'pending' : 'approved'),
                'receipt_no'   => $fee?->feeReceipt?->receipt_number,
            ];
        })->values();
    }

    private function rowFromParticipant(
        FestEvent $event,
        FestRegistration $registration,
        FestParticipant $participant,
        float $itemFee,
        ?FestSchoolEventFee $schoolFee,
        bool $feeRequired,
    ): array {
        $isTeacher = (bool) $participant->teacher_id;
        $name = $participant->student?->name ?? $participant->teacher?->name ?? '—';
        $regNo = $participant->student?->reg_no ?? $participant->teacher?->reg_no ?? '—';

        return [
            'registration_id'     => $registration->id,
            'participant_id'        => $participant->id,
            'school_id'             => $registration->school_id,
            'school_name'           => $registration->school?->name ?? $registration->school_id,
            'participant_name'      => $name,
            'participant_reg_no'    => $regNo,
            'level_reg'             => $participant->level_registration_number ?? '—',
            'item_reg'              => $participant->item_registration_number ?? '—',
            'item_id'               => $registration->item_id,
            'item_title'            => $registration->item?->title ?? '—',
            'head_id'               => $registration->item?->head_id,
            'head_name'             => $registration->item?->head?->name,
            'registration_status'   => $registration->status,
            'participant_role'      => $participant->participant_role ?? 'performer',
            'chest_no'              => $participant->chest_no ?? '—',
            'item_fee'              => $feeRequired ? round($itemFee, 2) : null,
            'school_total_due'      => $feeRequired ? (float) ($schoolFee?->total_due ?? 0) : null,
            'school_fee_status'     => $feeRequired ? ($schoolFee?->status ?? 'pending') : 'n/a',
            'is_teacher'            => $isTeacher,
        ];
    }
}
