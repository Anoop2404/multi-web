<?php

namespace App\Services\Events;

use App\Models\FestAthleticRecord;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRecordBreak;
use App\Models\Tenant;
use App\Services\Notifications\NotificationService;

class FestAthleticRecordService
{
    /** @return array{record_break: bool, break: ?FestRecordBreak, message: ?string} */
    public function evaluateMark(FestMark $mark): array
    {
        $event = FestEvent::find($mark->event_id);
        if (! $event?->record_tracking_enabled || blank($mark->measurement_value)) {
            return ['record_break' => false, 'break' => null, 'message' => null];
        }

        $item = FestEventItem::find($mark->item_id);
        if (! $item || (($item->category ?? '') !== 'sports' && ! $item->sport_discipline)) {
            return ['record_break' => false, 'break' => null, 'message' => null];
        }

        $participant = FestParticipant::with(['student', 'registration.school'])->find($mark->participant_id);
        if (! $participant) {
            return ['record_break' => false, 'break' => null, 'message' => null];
        }

        $classGroup = $item->class_group ?? 'open';
        $gender = $this->resolveGender($participant, $item);
        $direction = $this->resolveDirection($item);
        $newValue = $this->parseMeasurement((string) $mark->measurement_value);
        $unit = $mark->measurement_unit ?? $this->defaultUnit($item);

        $record = FestAthleticRecord::where('event_id', $event->id)
            ->where('item_id', $item->id)
            ->where('class_group', $classGroup)
            ->where('gender', $gender)
            ->first();

        if (! $record) {
            FestAthleticRecord::create([
                'event_id'              => $event->id,
                'item_id'               => $item->id,
                'class_group'           => $classGroup,
                'gender'                => $gender,
                'record_direction'      => $direction,
                'record_value'          => $newValue,
                'record_unit'           => $unit,
                'holder_name'           => $participant->student?->name ?? $participant->teacher?->name,
                'holder_school_id'      => $participant->registration?->school_id,
                'holder_participant_id' => $participant->id,
                'source_mark_id'        => $mark->id,
                'record_date'           => now()->toDateString(),
            ]);

            return ['record_break' => false, 'break' => null, 'message' => 'First recorded mark set as baseline.'];
        }

        if (! $this->isBetter($newValue, (float) $record->record_value, $record->record_direction)) {
            return ['record_break' => false, 'break' => null, 'message' => null];
        }

        $prizeLabel = $event->default_record_prize_label ?: 'Record Break Prize';

        $break = FestRecordBreak::create([
            'event_id'            => $event->id,
            'item_id'             => $item->id,
            'athletic_record_id'  => $record->id,
            'participant_id'      => $participant->id,
            'mark_id'             => $mark->id,
            'previous_value'      => $record->record_value,
            'new_value'           => $newValue,
            'record_unit'         => $unit,
            'prize_label'         => $prizeLabel,
            'prize_awarded'       => true,
            'broken_at'           => now(),
        ]);

        $record->update([
            'record_value'          => $newValue,
            'record_unit'           => $unit,
            'holder_name'           => $participant->student?->name ?? $participant->teacher?->name,
            'holder_school_id'      => $participant->registration?->school_id,
            'holder_participant_id' => $participant->id,
            'source_mark_id'        => $mark->id,
            'record_date'           => now()->toDateString(),
        ]);

        $ref = $mark->ref_data_json ?? [];
        $ref['record_break'] = true;
        $ref['record_break_id'] = $break->id;
        $mark->update(['ref_data_json' => $ref]);

        $this->notifyRecordBreak($event, $break, $participant);
        app(FestCertificateService::class)->issueRecordBreakCertificate($break);

        return [
            'record_break' => true,
            'break'        => $break,
            'message'      => "New record! {$prizeLabel} awarded.",
        ];
    }

    public function parseMeasurement(string $value): float
    {
        $value = trim($value);
        if (preg_match('/^(\d+):(\d+(?:\.\d+)?)$/', $value, $m)) {
            return round((float) $m[1] * 60 + (float) $m[2], 4);
        }

        return round((float) $value, 4);
    }

    public function isBetter(float $new, float $old, string $direction): bool
    {
        return $direction === 'lower_better' ? $new < $old : $new > $old;
    }

    private function resolveDirection(FestEventItem $item): string
    {
        $criteria = $item->criteria_json ?? [];
        if (($criteria['record_direction'] ?? null) === 'higher_better') {
            return 'higher_better';
        }

        $throws = ['shot_put', 'discus', 'javelin', 'long_jump', 'high_jump', 'triple_jump'];
        if (in_array($item->sport_discipline ?? '', $throws, true)) {
            return 'higher_better';
        }

        return 'lower_better';
    }

    private function defaultUnit(FestEventItem $item): string
    {
        return $this->resolveDirection($item) === 'higher_better' ? 'm' : 's';
    }

    private function resolveGender(FestParticipant $participant, FestEventItem $item): string
    {
        $g = $participant->student?->gender ?? $item->gender ?? 'open';

        return match (strtolower((string) $g)) {
            'male', 'm', 'boy' => 'male',
            'female', 'f', 'girl' => 'female',
            default => 'open',
        };
    }

    private function notifyRecordBreak(FestEvent $event, FestRecordBreak $break, FestParticipant $participant): void
    {
        $schoolId = $participant->registration?->school_id;
        if (! $schoolId) {
            return;
        }

        $school = Tenant::find($schoolId);
        $users = \App\Models\User::role(['school_admin', 'sahodaya_admin'])
            ->where(function ($q) use ($schoolId, $event) {
                $q->where('tenant_id', $schoolId)->orWhere('tenant_id', $event->tenant_id);
            })
            ->get();

        $item = FestEventItem::find($break->item_id);
        $service = app(NotificationService::class);

        foreach ($users as $user) {
            $service->notifyFromTemplate($user, 'fest.record.broken', [
                'event_title'   => $event->title,
                'item_title'    => $item?->title ?? 'Event',
                'student_name'  => $participant->student?->name ?? '',
                'school_name'   => $school?->name ?? '',
                'new_value'     => (string) $break->new_value,
                'record_unit'   => $break->record_unit ?? '',
                'prize_label'   => $break->prize_label,
            ]);
        }
    }
}
