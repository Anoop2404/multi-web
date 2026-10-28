<?php

namespace App\Http\Controllers\Concerns;

use App\Models\McqQuestion;
use App\Models\McqQuestionBank;
use App\Models\Subject;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Mcq\McqQuestionImporter;
use App\Services\Mcq\McqQuestionInput;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Spreadsheet\SpreadsheetWriter;
use App\Support\FestClassGroupScheme;
use App\Support\Mcq\McqQuestionBankScope;
use App\Support\TenantStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Question bank authoring for Talent Search (MCQ) exams, shared by the Sahodaya admin
 * panel, the exam portal, and school model-exam authoring. The using controller supplies
 * the Sahodaya, optional school scope, the URL base and how to render a page.
 */
trait ManagesMcqQuestionBanks
{
    abstract protected function bankSahodayaId(Request $request): string;

    abstract protected function bankBaseUrl(Request $request): string;

    /** @param  'index'|'show'  $page */
    abstract protected function renderBankPage(Request $request, string $page, array $props);

    /** When set, banks are scoped to this school (school model exams). */
    protected function bankSchoolId(Request $request): ?string
    {
        return null;
    }

    protected function bankTeacherId(Request $request): ?string
    {
        return null;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<\App\Models\McqQuestionBank>  $query */
    protected function scopeBanksQuery($query, Request $request)
    {
        $schoolId = $this->bankSchoolId($request);
        if ($schoolId !== null) {
            $query->where('school_id', $schoolId);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $sahodayaId = $this->bankSahodayaId($request);
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:'.implode(',', McqQuestionBank::STATUSES),
        ]);

        $classGroups = FestClassGroupScheme::labelsForSahodaya($sahodayaId);

        $banks = $this->scopeBanksQuery(McqQuestionBank::where('sahodaya_id', $sahodayaId), $request)
            ->with(['school:id,name', 'teacher:id,name', 'classCategory:id,label', 'masterClass:id,name,class_category_id'])
            ->withCount(['questions', 'exams'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $needle = '%'.mb_strtolower($search).'%';
                $q->where(fn ($inner) => $inner->whereRaw('LOWER(title) LIKE ?', [$needle])->orWhereRaw('LOWER(subject) LIKE ?', [$needle]));
            })
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (McqQuestionBank $bank) => $this->presentBankRow($bank, $classGroups));

        $allBanks = $this->scopeBanksQuery(McqQuestionBank::where('sahodaya_id', $sahodayaId), $request);
        $masterData = app(EffectiveMasterDataResolver::class);

        return $this->renderBankPage($request, 'index', [
            'banks'       => $banks,
            'filters'     => $filters,
            'stats'       => [
                'banks'     => (clone $allBanks)->count(),
                'ready'     => (clone $allBanks)->where('status', 'active')->count(),
                'questions' => McqQuestion::whereIn('bank_id', (clone $allBanks)->select('id'))->count(),
            ],
            'subjects'    => $this->subjectOptions($sahodayaId),
            'classGroups' => collect($classGroups)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])->values(),
            'classCategories' => $masterData->classCategories($sahodayaId)->map(fn ($c) => [
                'id' => $c->id, 'label' => $c->label,
            ])->values(),
            'masterClasses' => $masterData->masterClasses($sahodayaId)->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'class_category_id' => $c->class_category_id,
            ])->values(),
            'baseUrl'     => $this->bankBaseUrl($request),
        ]);
    }

    public function storeBank(Request $request): RedirectResponse
    {
        $sahodayaId = $this->bankSahodayaId($request);
        $data = $this->validateBank($request);

        $bank = McqQuestionBank::create([
            ...$data,
            'sahodaya_id'        => $sahodayaId,
            'school_id'          => $this->bankSchoolId($request),
            'teacher_id'         => $this->bankTeacherId($request),
            'status'             => 'draft',
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->auditBank($bank, 'mcq.question_bank.created', "Created question bank \"{$bank->title}\"");

        return redirect($this->bankBaseUrl($request).'/'.$bank->id)->with('success', 'Question bank created. Add questions, then mark it Ready.');
    }

    public function show(Request $request, string $tenantId, McqQuestionBank $bank)
    {
        $this->assertBankInSahodaya($request, $bank);
        $bank->load(['questions', 'exams:id,title,status', 'school:id,name', 'teacher:id,name', 'classCategory:id,label', 'masterClass:id,name,class_category_id']);
        $classGroups = FestClassGroupScheme::labelsForSahodaya($bank->sahodaya_id);
        $baseUrl = $this->bankBaseUrl($request);
        $masterData = app(EffectiveMasterDataResolver::class);

        $questions = $bank->questions->values()->map(fn (McqQuestion $q, int $i) => [
            'id'            => $q->id,
            'number'        => $i + 1,
            'type'          => $q->typeValue(),
            'type_label'    => McqQuestion::TYPE_LABELS[$q->typeValue()],
            'title'         => $q->title,
            'body_text'     => $q->body_text,
            'options'       => $this->presentOptions($q, "{$baseUrl}/{$bank->id}/questions/{$q->id}/image"),
            'show_count'    => $q->showsCorrectCount(),
            'correct_keys'  => $q->typeValue() === McqQuestion::TYPE_NUMERIC ? [] : $q->correctKeys(),
            'answer_label'  => $q->answerKeyLabel(),
            'marks'         => $q->marksValue(),
            'negative_mark' => $q->negativeMarkValue(),
            'difficulty'    => $q->difficulty,
            'explanation'   => $q->explanation,
            'gradable'      => $q->isGradable(),
            'image_url'     => $q->document_path ? "{$baseUrl}/{$bank->id}/questions/{$q->id}/image" : null,
            'form'          => McqQuestionInput::toForm($q),
        ]);

        return $this->renderBankPage($request, 'show', [
            'bank'        => array_merge($this->presentBankRow($bank, $classGroups), [
                'description' => $bank->description,
                'locked'      => $bank->isLockedByExam(),
                'exams'       => $bank->exams->map(fn ($exam) => $exam->only('id', 'title', 'status'))->values(),
            ]),
            'questions'   => $questions,
            'stats'       => [
                'total'      => $questions->count(),
                'gradable'   => $questions->where('gradable', true)->count(),
                'max_score'  => round($bank->questions->filter->isGradable()->sum(fn (McqQuestion $q) => $q->marksValue()), 2),
                'by_type'    => collect(McqQuestion::TYPES)->mapWithKeys(fn ($t) => [$t => $questions->where('type', $t)->count()]),
            ],
            'typeLabels'   => McqQuestion::TYPE_LABELS,
            'difficulties' => McqQuestion::DIFFICULTIES,
            'subjects'     => $this->subjectOptions($bank->sahodaya_id),
            'classGroups'  => collect($classGroups)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])->values(),
            'classCategories' => $masterData->classCategories($bank->sahodaya_id)->map(fn ($c) => [
                'id' => $c->id, 'label' => $c->label,
            ])->values(),
            'masterClasses' => $masterData->masterClasses($bank->sahodaya_id)->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'class_category_id' => $c->class_category_id,
            ])->values(),
            'baseUrl'      => $baseUrl,
        ]);
    }

    public function updateBank(Request $request, string $tenantId, McqQuestionBank $bank): RedirectResponse
    {
        $this->assertBankInSahodaya($request, $bank);
        $data = $this->validateBank($request, withStatus: true);

        if (($data['status'] ?? $bank->status) === 'active' && $bank->status !== 'active'
            && ! $bank->questions()->get()->contains(fn (McqQuestion $q) => $q->isGradable())) {
            return back()->with('error', 'Add at least one question with an answer key before marking the bank Ready.');
        }

        if (($data['status'] ?? null) === 'archived' && $bank->exams()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
            return back()->with('error', 'This bank is on an exam paper that hasn\'t finished. Remove it from that exam first.');
        }

        $before = $bank->only('title', 'status');
        $bank->update($data);
        $this->auditBank($bank, 'mcq.question_bank.updated', "Updated question bank \"{$bank->title}\"", ['before' => $before]);

        return back()->with('success', 'Question bank saved.');
    }

    public function destroyBank(Request $request, string $tenantId, McqQuestionBank $bank): RedirectResponse
    {
        $this->assertBankInSahodaya($request, $bank);

        if ($bank->exams()->exists()) {
            return back()->with('error', 'This bank is used on an exam paper. Remove it from the exam (or archive the bank) instead.');
        }

        DB::transaction(function () use ($bank) {
            foreach ($bank->questions as $question) {
                $this->deleteImages($question->imagePaths());
            }
            $bank->questions()->delete();
            $bank->delete();
        });

        $this->auditBank($bank, 'mcq.question_bank.deleted', "Deleted question bank \"{$bank->title}\"");

        return redirect($this->bankBaseUrl($request))->with('success', 'Question bank deleted.');
    }

    public function storeQuestion(Request $request, string $tenantId, McqQuestionBank $bank): RedirectResponse
    {
        $this->assertBankInSahodaya($request, $bank);
        if ($response = $this->rejectIfLocked($bank)) {
            return $response;
        }

        $this->validateImages($request);
        $attributes = McqQuestionInput::normalize($this->questionInput($request, null), $request->hasFile('image'));
        $attributes = $this->storePendingOptionImages($request, $bank, $attributes);

        $question = $bank->questions()->create([
            ...$attributes,
            'document_path'      => $request->hasFile('image') ? $this->storeImage($request->file('image'), $bank) : null,
            'display_order'      => ((int) $bank->questions()->max('display_order')) + 1,
            'created_by_user_id' => $request->user()->id,
        ]);
        $bank->touch();

        $this->auditQuestion($bank, $question, 'mcq.question.created', 'Added a question', ['answer' => $question->answerKeyLabel()]);

        return back()->with('success', 'Question added.');
    }

    public function updateQuestion(Request $request, string $tenantId, McqQuestionBank $bank, McqQuestion $question): RedirectResponse
    {
        $this->assertBankInSahodaya($request, $bank);
        abort_unless($question->bank_id === $bank->id, 404);
        if ($response = $this->rejectIfLocked($bank)) {
            return $response;
        }

        $this->validateImages($request);
        $request->validate(['remove_image' => 'nullable|boolean']);
        $keepsImage = $question->document_path && ! $request->boolean('remove_image');
        $attributes = McqQuestionInput::normalize($this->questionInput($request, $question), $request->hasFile('image') || $keepsImage);
        $attributes = $this->storePendingOptionImages($request, $bank, $attributes);

        $answerBefore = $question->answerKeyLabel();
        $pathsBefore = $question->imagePaths();

        if ($request->hasFile('image')) {
            $attributes['document_path'] = $this->storeImage($request->file('image'), $bank);
        } elseif ($request->boolean('remove_image')) {
            $attributes['document_path'] = null;
        }

        $question->update($attributes);
        $bank->touch();
        $this->deleteImages(array_diff($pathsBefore, $question->fresh()->imagePaths()));

        $answerAfter = $question->fresh()->answerKeyLabel();
        $this->auditQuestion(
            $bank,
            $question,
            $answerBefore !== $answerAfter ? 'mcq.question.answer_changed' : 'mcq.question.updated',
            $answerBefore !== $answerAfter ? "Changed answer key from {$answerBefore} to {$answerAfter}" : 'Edited a question',
            ['answer_before' => $answerBefore, 'answer_after' => $answerAfter],
        );

        return back()->with('success', 'Question saved.');
    }

    public function destroyQuestion(Request $request, string $tenantId, McqQuestionBank $bank, McqQuestion $question): RedirectResponse
    {
        $this->assertBankInSahodaya($request, $bank);
        abort_unless($question->bank_id === $bank->id, 404);
        if ($response = $this->rejectIfLocked($bank)) {
            return $response;
        }

        $this->deleteImages($question->imagePaths());
        $question->delete();
        $bank->touch();

        $this->auditQuestion($bank, $question, 'mcq.question.deleted', 'Deleted a question', ['answer' => $question->answerKeyLabel()]);

        return back()->with('success', 'Question deleted.');
    }

    public function moveQuestion(Request $request, string $tenantId, McqQuestionBank $bank, McqQuestion $question): RedirectResponse
    {
        $this->assertBankInSahodaya($request, $bank);
        abort_unless($question->bank_id === $bank->id, 404);
        if ($response = $this->rejectIfLocked($bank)) {
            return $response;
        }

        $direction = $request->validate(['direction' => 'required|in:up,down'])['direction'];
        $ordered = $bank->questions()->orderBy('display_order')->orderBy('id')->get()->values();
        $index = $ordered->search(fn (McqQuestion $q) => $q->id === $question->id);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && isset($ordered[$swap])) {
            $items = $ordered->all();
            [$items[$index], $items[$swap]] = [$items[$swap], $items[$index]];
            DB::transaction(function () use ($items) {
                foreach (array_values($items) as $position => $item) {
                    $item->update(['display_order' => $position + 1]);
                }
            });
        }

        return back();
    }

    public function questionImage(Request $request, string $tenantId, McqQuestionBank $bank, McqQuestion $question)
    {
        $this->assertBankInSahodaya($request, $bank);
        abort_unless($question->bank_id === $bank->id, 404);
        $path = $this->imagePathFor($question, $request->query('option'));
        abort_unless($path, 404);

        return TenantStorage::downloadPrivate($path, inline: true);
    }

    public function importTemplate(Request $request)
    {
        $this->bankSahodayaId($request);
        $xlsx = SpreadsheetWriter::xlsx(McqQuestionImporter::template());

        return response()->streamDownload(
            fn () => print $xlsx,
            'question-import-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function importPreview(Request $request, string $tenantId, McqQuestionBank $bank, McqQuestionImporter $importer): RedirectResponse
    {
        $this->assertBankInSahodaya($request, $bank);
        if ($response = $this->rejectIfLocked($bank)) {
            return $response;
        }

        $request->validate(['file' => 'required|file|mimes:xlsx,csv,txt|max:5120']);
        $preview = $importer->preview($request->file('file')->getRealPath());

        $token = Str::random(32);
        Cache::put($this->importCacheKey($bank, $token), [
            'user_id' => $request->user()->id,
            'valid'   => $preview['valid'],
        ], now()->addMinutes(30));

        return back()->with('mcqImportPreview', [
            'token'       => $token,
            'filename'    => $request->file('file')->getClientOriginalName(),
            'rows'        => $preview['rows'],
            'total'       => $preview['total'],
            'valid_count' => count($preview['valid']),
            'error_count' => $preview['error_count'],
        ]);
    }

    public function importCommit(Request $request, string $tenantId, McqQuestionBank $bank): RedirectResponse
    {
        $this->assertBankInSahodaya($request, $bank);
        if ($response = $this->rejectIfLocked($bank)) {
            return $response;
        }

        $token = $request->validate(['token' => 'required|string|size:32'])['token'];
        $cached = Cache::pull($this->importCacheKey($bank, $token));
        if (! $cached || ($cached['user_id'] ?? null) !== $request->user()->id) {
            return back()->with('error', 'That import preview has expired. Upload the file again.');
        }

        $count = DB::transaction(function () use ($bank, $cached, $request) {
            $order = (int) $bank->questions()->max('display_order');
            foreach ($cached['valid'] as $attributes) {
                $bank->questions()->create([
                    ...$attributes,
                    'display_order'      => ++$order,
                    'created_by_user_id' => $request->user()->id,
                ]);
            }

            return count($cached['valid']);
        });
        $bank->touch();

        $this->auditBank($bank, 'mcq.question.imported', "Imported {$count} question(s) into \"{$bank->title}\"", ['count' => $count]);

        return back()->with('success', "Imported {$count} question(s).");
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function assertBankInSahodaya(Request $request, McqQuestionBank $bank): void
    {
        abort_unless($bank->sahodaya_id === $this->bankSahodayaId($request), 404);

        $schoolId = $this->bankSchoolId($request);
        if ($schoolId !== null) {
            abort_unless($bank->school_id === $schoolId, 404);
        }
    }

    private function rejectIfLocked(McqQuestionBank $bank): ?RedirectResponse
    {
        return $bank->isLockedByExam()
            ? back()->with('error', 'Students have started an exam that uses this bank, so its questions can no longer be changed.')
            : null;
    }

    /** @return array<string, mixed> */
    private function validateBank(Request $request, bool $withStatus = false): array
    {
        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'subject'            => 'required|string|max:120',
            'class_group'        => 'nullable|string|max:80',
            'class_category_id'  => 'nullable|integer',
            'master_class_id'    => 'nullable|integer',
            'description'        => 'nullable|string|max:2000',
        ] + ($withStatus ? ['status' => 'required|in:'.implode(',', McqQuestionBank::STATUSES)] : []));

        $sahodayaId = $this->bankSahodayaId($request);
        $masterData = app(EffectiveMasterDataResolver::class);

        if (! empty($data['class_category_id'])) {
            $ok = $masterData->classCategories($sahodayaId)->contains('id', (int) $data['class_category_id']);
            if (! $ok) {
                throw ValidationException::withMessages(['class_category_id' => 'Choose a class category from this Sahodaya.']);
            }
        } else {
            $data['class_category_id'] = null;
        }

        if (! empty($data['master_class_id'])) {
            $master = $masterData->masterClasses($sahodayaId)->firstWhere('id', (int) $data['master_class_id']);
            if (! $master) {
                throw ValidationException::withMessages(['master_class_id' => 'Choose a class from this Sahodaya.']);
            }
            // If both set, class must belong to the chosen category (or fill category from class).
            if ($data['class_category_id'] !== null && (int) $master->class_category_id !== (int) $data['class_category_id']) {
                throw ValidationException::withMessages([
                    'master_class_id' => 'That class is not in the selected class category.',
                ]);
            }
            if ($data['class_category_id'] === null && $master->class_category_id) {
                $data['class_category_id'] = (int) $master->class_category_id;
            }
        } else {
            $data['master_class_id'] = null;
        }

        if (($data['class_group'] ?? null) === '') {
            $data['class_group'] = null;
        }

        return $data;
    }

    /** @param  array<string|int, string>  $classGroups */
    private function presentBankRow(McqQuestionBank $bank, array $classGroups): array
    {
        $scopeLabel = McqQuestionBankScope::label($bank);
        $groupLabel = $bank->class_group ? FestClassGroupScheme::resolveItemLabel($classGroups, $bank->class_group) : null;

        return [
            'id'                   => $bank->id,
            'title'                => $bank->title,
            'subject'              => $bank->subject,
            'class_group'          => $bank->class_group,
            'class_group_label'    => $groupLabel,
            'class_category_id'    => $bank->class_category_id,
            'master_class_id'      => $bank->master_class_id,
            'class_category_label' => $bank->classCategory?->label,
            'master_class_label'   => $bank->masterClass?->name,
            'scope_label'          => $scopeLabel ?? $groupLabel,
            'status'               => $bank->status,
            'status_label'         => McqQuestionBank::STATUS_LABELS[$bank->status] ?? $bank->status,
            'owner_label'          => $this->bankOwnerLabel($bank),
            'questions_count'      => $bank->questions_count ?? $bank->questions()->count(),
            'exams_count'          => $bank->exams_count ?? $bank->exams()->count(),
            'updated_at'           => $bank->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The editor's fields, with each option's image resolved on the server: a new upload is
     * marked "upload:<key>" until validation passes (see storePendingOptionImages()), a kept
     * image is looked up on the stored question, and anything the client sends as a path is ignored.
     *
     * @return array<string, mixed>
     */
    private function questionInput(Request $request, ?McqQuestion $existing): array
    {
        $input = $request->only([
            'type', 'title', 'body_text', 'correct_keys', 'show_count', 'answer_value', 'answer_tolerance',
            'marks', 'negative_mark', 'explanation', 'difficulty',
        ]);

        $existingImages = collect($existing?->options_json ?? [])
            ->mapWithKeys(fn ($o) => [strtolower((string) ($o['key'] ?? '')) => $o['image_path'] ?? null]);

        $input['options'] = collect((array) $request->input('options', []))
            ->values()
            ->map(function ($option, int $index) use ($request, $existingImages) {
                $key = strtolower(trim((string) ($option['key'] ?? McqQuestion::OPTION_KEYS[$index] ?? '')));
                $image = null;
                if ($key !== '' && $request->hasFile("option_images.{$key}")) {
                    $image = "upload:{$key}";
                } elseif (filter_var($option['keep_image'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $image = $existingImages[$key] ?? null;
                }

                return ['key' => $key, 'label' => (string) ($option['label'] ?? ''), 'image_path' => $image];
            })
            ->all();

        return $input;
    }

    private function validateImages(Request $request): void
    {
        $request->validate([
            'image'           => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'option_images'   => 'nullable|array|max:6',
            'option_images.*' => 'image|mimes:jpg,jpeg,png,webp,gif|max:5120',
        ]);
    }

    /** Swaps the "upload:<key>" markers left by questionInput() for stored file paths. */
    private function storePendingOptionImages(Request $request, McqQuestionBank $bank, array $attributes): array
    {
        if (! is_array($attributes['options_json'] ?? null)) {
            return $attributes;
        }

        $attributes['options_json'] = array_map(function (array $option) use ($request, $bank) {
            $path = $option['image_path'] ?? '';
            if (str_starts_with($path, 'upload:')) {
                $option['image_path'] = $this->storeImage($request->file('option_images.'.substr($path, 7)), $bank);
            }

            return $option;
        }, $attributes['options_json']);

        return $attributes;
    }

    private function storeImage($file, McqQuestionBank $bank): string
    {
        return TenantStorage::storeUploadedFile($file, "sahodayas/{$bank->sahodaya_id}/mcq-banks/{$bank->id}");
    }

    /** @param  iterable<string>  $paths */
    private function deleteImages(iterable $paths): void
    {
        foreach ($paths as $path) {
            try {
                Storage::disk(TenantStorage::uploadDisk())->delete($path);
            } catch (\Throwable) {
                // A missing file must never block editing the question.
            }
        }
    }

    private function imagePathFor(McqQuestion $question, mixed $optionKey): ?string
    {
        if (! is_string($optionKey) || $optionKey === '') {
            return $question->document_path;
        }

        return collect($question->options_json ?? [])
            ->first(fn ($o) => strtolower((string) ($o['key'] ?? '')) === strtolower($optionKey))['image_path'] ?? null;
    }

    /** @return list<array{key: string, label: string, image_url: ?string}> */
    private function presentOptions(McqQuestion $question, string $imageUrl): array
    {
        return collect($question->options_json ?? [])
            ->map(fn ($o) => [
                'key'       => strtolower((string) ($o['key'] ?? '')),
                'label'     => (string) ($o['label'] ?? ''),
                'image_url' => filled($o['image_path'] ?? null) ? $imageUrl.'?option='.strtolower((string) $o['key']) : null,
            ])
            ->values()
            ->all();
    }

    private function importCacheKey(McqQuestionBank $bank, string $token): string
    {
        return "mcq-import:{$bank->id}:{$token}";
    }

    private function bankOwnerLabel(McqQuestionBank $bank): string
    {
        if ($bank->teacher_id || $bank->school_id) {
            return trim(($bank->teacher?->name ?? 'Teacher').' · '.($bank->school?->name ?? 'School'), ' ·');
        }

        return 'Sahodaya';
    }

    /** @return list<array{value: string, label: string}> */
    private function subjectOptions(string $sahodayaId): array
    {
        $subjects = Subject::query()->forSahodaya($sahodayaId)->active()->orderBy('label')->get(['code', 'label']);
        if ($subjects->isEmpty()) {
            $subjects = Subject::query()->forSahodaya($sahodayaId)->orderBy('label')->get(['code', 'label']);
        }

        return $subjects->map(fn ($s) => ['value' => (string) $s->label, 'label' => (string) $s->label])->unique('value')->values()->all();
    }

    private function auditBank(McqQuestionBank $bank, string $action, string $description, array $properties = []): void
    {
        app(PlatformAuditLogger::class)->log(
            $action,
            $description,
            $bank,
            ['bank_id' => $bank->id, 'tenant_id' => $bank->sahodaya_id] + $properties,
            category: 'mcq',
            tenantId: $bank->sahodaya_id,
        );
    }

    private function auditQuestion(McqQuestionBank $bank, McqQuestion $question, string $action, string $description, array $properties = []): void
    {
        app(PlatformAuditLogger::class)->log(
            $action,
            "{$description} in \"{$bank->title}\"",
            $question,
            ['bank_id' => $bank->id, 'question_id' => $question->id, 'tenant_id' => $bank->sahodaya_id] + $properties,
            category: 'mcq',
            tenantId: $bank->sahodaya_id,
        );
    }
}
