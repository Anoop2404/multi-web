<?php

namespace App\Services\Mcq;

use App\Models\{McqExam, McqExamPaperAssignment, McqPaperTemplate, McqQuestion, McqQuestionBank, McqRegistration};
use App\Support\Mcq\McqClassKey;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class McqPaperTemplateService
{
    /** Resolve a template version immediately; later bank edits never rewrite it. */
    public function save(string $tenantId, array $data, ?McqPaperTemplate $template = null): McqPaperTemplate
    {
        abort_if($template && $template->tenant_id !== $tenantId, 403);
        $questions = collect();
        $seen = [];
        foreach ($data['sections'] as $index => $section) {
            $bank = McqQuestionBank::where('sahodaya_id', $tenantId)->where('status', 'active')->findOrFail($section['bank_id']);
            $pool = $bank->questions()->get();
            $mode = $section['pick_mode'];
            $count = (int) ($section['pick_count'] ?? 0);
            if ($mode === 'manual') {
                $ids = $section['question_ids'] ?? [];
                $byId = $pool->keyBy('id');
                foreach ($ids as $id) {
                    if (! $byId->has($id)) $this->fail('A selected question is not in its bank.');
                }
                $pool = collect($ids)->map(fn ($id) => $byId[$id]);
            } elseif ($mode !== 'all') {
                if ($count < 1 || $count > $pool->count()) $this->fail('The requested question count exceeds the available bank questions.');
                if ($mode === 'random') $pool = collect(McqPaperService::seededShuffle($pool->all(), (string) Str::uuid()));
                $pool = $pool->take($count);
            }
            foreach ($pool as $question) {
                if (isset($seen[$question->id])) $this->fail('A question cannot appear twice in the same template.');
                if (! $question->isGradable()) $this->fail('Every selected question needs a valid answer key.');
                $seen[$question->id] = true;
                $row = $question->toArray();
                $row['section_title'] = $section['title'] ?? $bank->title;
                $row['section_index'] = $index;
                if (isset($section['marks'])) $row['marks'] = $section['marks'];
                if (isset($section['negative_mark'])) $row['negative_mark'] = $section['negative_mark'];
                $questions->push($row);
            }
        }
        if ($questions->count() > 1000) $this->fail('A template can contain at most 1,000 questions.');
        if ($questions->isEmpty()) $this->fail('Select at least one question.');
        // Copy image bytes to immutable version paths, outside mutable bank storage.
        $versionPath = 'mcq-paper-versions/'.$tenantId.'/'.Str::uuid();
        $copy = function (?string $path) use ($versionPath): ?string {
            if (! $path) return null;
            $bytes = TenantStorage::get($path);
            if ($bytes === null) $this->fail('A selected question image is missing. Replace it before saving.');
            $target = $versionPath.'/'.Str::uuid().'.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'bin');
            TenantStorage::put($target, $bytes);
            return $target;
        };
        $rows = $questions->map(function ($row) use ($copy) {
            $row['document_path'] = $copy($row['document_path'] ?? null);
            $row['options_json'] = collect($row['options_json'] ?? [])->map(function ($option) use ($copy) {
                if (! empty($option['image_path'])) $option['image_path'] = $copy($option['image_path']);
                return $option;
            })->all();
            return $row;
        })->all();
        return DB::transaction(function () use ($tenantId, $data, $template, $rows) {
            if ($template) $template = McqPaperTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();
            $values = ['title' => $data['title'], 'definition_json' => $data['sections'], 'questions_json' => $rows,
                'total_marks' => round(collect($rows)->sum(fn ($q) => (float) $q['marks']), 2),
                'version' => ($template?->version ?? 0) + 1, 'is_active' => true];
            if ($template) { $template->update($values); return $template; }
            return McqPaperTemplate::create(['tenant_id' => $tenantId] + $values);
        });
    }

    public function assign(McqExam $exam, array $data): void
    {
        DB::transaction(function () use ($exam, $data) {
            $exam = McqExam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            if (McqRegistration::where('exam_id', $exam->id)->whereNotNull('started_at')->exists()) $this->fail('Candidates have started. Create another exam to change class papers.');
            if (($exam->settings_json['engine_state'] ?? null) === 'armed') $this->fail('Disarm the exam engine before changing its paper.');
            $rows = []; $classes = [];
            foreach ($data['assignments'] as $row) {
                $template = McqPaperTemplate::where('tenant_id', $exam->tenant_id)->where('is_active', true)->findOrFail($row['template_id']);
                $keys = [];
                foreach ($row['classes'] as $class) {
                    $key = McqClassKey::normalize($class);
                    if ($key === '' || isset($classes[$key])) $this->fail('Assign each class to exactly one paper template.');
                    $classes[$key] = true; $keys[] = $key;
                }
                if (isset($row['total_marks']) && abs((float) $row['total_marks'] - (float) $template->total_marks) > 0.001) {
                    $this->fail('Class total marks must equal the template question marks ('.$template->total_marks.'). Adjust template marks first.');
                }
                $rows[] = ['classes' => $keys, 'template_id' => $template->id, 'version' => $template->version,
                    'title' => $template->title, 'questions' => $template->questions_json,
                    'total_marks' => (float) $template->total_marks,
                    'pass_mark' => $row['pass_mark'] ?? $exam->pass_mark ?? 0,
                    'shuffle_questions' => $row['shuffle_questions'], 'shuffle_options' => (bool) $row['shuffle_options']];
            }
            McqRegistration::where('exam_id', $exam->id)->whereNull('started_at')->update(['paper_snapshot' => null]);
            McqExamPaperAssignment::where('exam_id', $exam->id)->delete();
            $rows = array_map(function ($row) use ($exam) {
                $questions = $row['questions']; unset($row['questions']);
                McqExamPaperAssignment::create(['exam_id' => $exam->id, 'mapping_json' => $row, 'questions_json' => $questions]);
                return $row;
            }, $rows);
            $settings = $exam->settings_json ?? [];
            $settings['paper_assignments'] = $rows;
            $settings['paper_fallback'] = $data['fallback'];
            $exam->update(['settings_json' => $settings]);
        });
    }

    public function assignment(McqRegistration $registration): ?array
    {
        $key = McqClassKey::normalize($registration->student?->schoolClass?->name ?? ($registration->teacher_id ? 'Teacher' : 'Unassigned'));
        $assignment = McqExamPaperAssignment::where('exam_id', $registration->exam_id)->get()
            ->first(fn ($row) => in_array($key, $row->mapping_json['classes'], true));
        return $assignment ? $assignment->mapping_json + ['questions' => $assignment->questions_json] : null;
    }

    public function fail(string $message): never
    {
        throw ValidationException::withMessages(['paper_templates' => $message]);
    }
}
