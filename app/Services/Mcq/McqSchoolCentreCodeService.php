<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class McqSchoolCentreCodeService
{
    public function schools(McqExam $exam): array
    {
        $counts = McqRegistration::where('exam_id', $exam->id)->where('status', '!=', 'cancelled')
            ->selectRaw('school_id, COUNT(*) as candidates')->groupBy('school_id')->pluck('candidates', 'school_id');
        return Tenant::where('parent_id', $exam->tenant_id)->where('type', 'school')->orderByRaw('LOWER(name)')->orderBy('id')
            ->get(['id', 'name'])->map(fn ($school) => [
                'id' => $school->id, 'name' => $school->name, 'count' => (int) ($counts[$school->id] ?? 0),
                'code' => $exam->settings_json['school_centre_codes'][$school->id] ?? '',
            ])->all();
    }

    public function plan(McqExam $exam, array $input): array
    {
        $schools = $this->schools($exam);
        $replace = (bool) ($input['replace'] ?? false);
        $targets = array_filter($schools, fn ($row) => ($input['scope'] ?? 'registered') === 'all' || $row['count'] > 0);
        $targetIds = array_column($targets, 'id');
        $used = [];
        foreach (($exam->settings_json['school_centre_codes'] ?? []) as $schoolId => $code) {
            if ($code !== '' && (! $replace || ! in_array($schoolId, $targetIds, true))) $used[strtolower($code)] = true;
        }
        $number = (int) $input['start'];
        $rows = [];
        foreach ($targets as $row) {
            $row['proposed'] = $row['code'];
            if ($replace || $row['code'] === '') {
                do {
                    $code = ($input['prefix'] ?? '').str_pad((string) $number++, (int) ($input['digits'] ?? 3), '0', STR_PAD_LEFT);
                } while (isset($used[strtolower($code)]));
                $used[strtolower($code)] = true;
                $row['proposed'] = $code;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    public function assign(McqExam $exam, array $input): int
    {
        return DB::transaction(function () use ($exam, $input) {
            $locked = McqExam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $rows = $this->plan($locked, $input);
            $settings = $locked->settings_json ?? [];
            $codes = $settings['school_centre_codes'] ?? [];
            $backup = 'mcq-centre-code-backups/'.$exam->tenant_id.'-'.$exam->id.'-'.now()->format('Ymd-His-u').'.json';
            if (! Storage::disk('local')->put($backup, json_encode($codes, JSON_THROW_ON_ERROR))) {
                throw new \RuntimeException('Centre code backup failed. No changes made.');
            }
            $changed = 0;
            foreach ($rows as $row) {
                if ($row['proposed'] !== $row['code']) $changed++;
                $codes[$row['id']] = $row['proposed'];
            }
            $settings['school_centre_codes'] = $codes;
            $settings['centre_code_numbering'] = $input;
            $locked->update(['settings_json' => $settings]);
            return $changed;
        });
    }
}
