<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class McqClassRollRangeService
{
    private function groups(McqExam $exam)
    {
        return McqRegistration::where('exam_id', $exam->id)->where('status', '!=', 'cancelled')
            ->with(['school:id,name', 'student:id,name,school_class_id', 'student.schoolClass:id,name'])
            ->get()->groupBy(fn ($row) => $row->student?->schoolClass?->name ?? 'Unassigned');
    }

    public function preview(McqExam $exam): array
    {
        $counts = DB::table('mcq_registrations as r')
            ->leftJoin('students as s', 's.id', '=', 'r.student_id')
            ->leftJoin('school_classes as c', 'c.id', '=', 's.school_class_id')
            ->where('r.exam_id', $exam->id)->where('r.status', '!=', 'cancelled')
            ->selectRaw("COALESCE(c.name, 'Unassigned') as class_name, COUNT(*) as candidate_count")
            ->groupBy('c.name')->get();

        return $counts->map(function ($row) use ($exam) {
            $class = $row->class_name;
            $preset = self::startForClass((string) $class);
            $start = $exam->settings_json['hall_ticket_class_starts'][$class] ?? $preset ?? $exam->next_hall_ticket_no;
            return ['class' => (string) $class, 'count' => (int) $row->candidate_count, 'start' => (int) $start, 'preset' => $preset];
        })->sortBy(fn ($row) => $row['preset'] ?? PHP_INT_MAX)->values()->all();
    }

    public function apply(McqExam $exam, array $starts): array
    {
        return DB::transaction(function () use ($exam, $starts) {
            $exam = McqExam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $groups = $this->groups($exam);
            foreach ($groups as $class => $group) {
                if (! isset($starts[$class]) || ! is_numeric($starts[$class]) || (int) $starts[$class] < 1) {
                    throw ValidationException::withMessages(['ranges' => 'Set a valid starting number for every active class. Refresh if registrations changed.']);
                }
            }
            $starts = array_intersect_key($starts, $groups->all());
            $rows = $groups->flatten(1);
            $backup = 'mcq-roll-backups/'.$exam->tenant_id.'-'.$exam->id.'-'.now()->format('Ymd-His-u').'.json';
            if (! Storage::disk('local')->put($backup, json_encode([
                'exam_id' => $exam->id,
                'previous_class_starts' => $exam->settings_json['hall_ticket_class_starts'] ?? null,
                'numbers' => $rows->pluck('hall_ticket_no', 'id')->all(),
            ], JSON_THROW_ON_ERROR))) {
                throw new \RuntimeException('Could not save roll-number backup. No changes made.');
            }
            foreach ($groups as $class => $group) {
                $sorted = $group->sort(function ($a, $b) {
                    return strcasecmp($a->school?->name ?? '', $b->school?->name ?? '')
                        ?: strcasecmp($a->student?->name ?? '', $b->student?->name ?? '')
                        ?: ($a->id <=> $b->id);
                })->values();
                foreach ($sorted as $index => $registration) {
                    DB::table('mcq_registrations')->where('id', $registration->id)
                        ->update(['hall_ticket_no' => (string) ($starts[$class] + $index)]);
                }
            }
            $settings = $exam->settings_json ?? [];
            $settings['hall_ticket_class_starts'] = $starts;
            $exam->update(['settings_json' => $settings]);

            return ['renumbered' => $rows->count(), 'backup' => $backup];
        });
    }

    public static function startForClass(string $class): ?int
    {
        $key = strtoupper(trim(preg_replace('/^CLASS\s*/i', '', trim($class))));
        if ($key === 'LKG') return 10000;
        if ($key === 'UKG') return 11000;
        return ctype_digit($key) && (int) $key >= 1 && (int) $key <= 10
            ? 11000 + (int) $key * 1000 : null;
    }
}
