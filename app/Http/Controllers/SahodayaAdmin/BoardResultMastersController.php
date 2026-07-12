<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\ApiConfig;
use App\Models\ExamStream;
use App\Models\Topper;
use App\Models\TopperCountConfig;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BoardResultMastersController extends SahodayaAdminController
{
    public function index()
    {
        $streams = ExamStream::query()
            ->forSahodaya($this->sahodaya->id)
            ->orderByRaw('sahodaya_id is null')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        $apiConfig = ApiConfig::forSahodaya($this->sahodaya->id);

        return $this->inertia('Sahodaya/BoardResults/Masters', [
            'streams' => $streams,
            'apiConfig' => $apiConfig->only([
                'id', 'weight_pass_percent', 'weight_distinctions',
                'weight_highest_mark', 'weight_toppers', 'is_active',
            ]),
            'topperConfigs' => TopperCountConfig::query()
                ->where('sahodaya_id', $this->sahodaya->id)
                ->orderBy('class')
                ->get(),
        ]);
    }

    public function storeStream(Request $request)
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('exam_streams', 'code')->where(fn ($q) => $q->where('sahodaya_id', $this->sahodaya->id)),
            ],
            'label' => 'required|string|max:120',
            'examination_type' => 'nullable|string|max:40',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'default_subjects' => 'nullable|array',
            'default_subjects.*' => 'string|max:120',
        ]);

        ExamStream::create([
            'sahodaya_id' => $this->sahodaya->id,
            'code' => strtoupper(trim($data['code'])),
            'label' => $data['label'],
            'examination_type' => $data['examination_type'] ?? 'board',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'default_subjects' => $data['default_subjects'] ?? [],
        ]);

        return back()->with('success', 'Exam stream created for this Sahodaya.');
    }

    public function updateStream(Request $request, string $tenantId, ExamStream $stream)
    {
        abort_unless(
            $stream->sahodaya_id === null || $stream->sahodaya_id === $this->sahodaya->id,
            403
        );

        // Global rows are cloned into a Sahodaya override instead of mutating the shared default.
        if ($stream->sahodaya_id === null) {
            $data = $request->validate([
                'label' => 'required|string|max:120',
                'examination_type' => 'nullable|string|max:40',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
                'default_subjects' => 'nullable|array',
                'default_subjects.*' => 'string|max:120',
            ]);

            ExamStream::updateOrCreate(
                ['sahodaya_id' => $this->sahodaya->id, 'code' => $stream->code],
                [
                    'label' => $data['label'],
                    'examination_type' => $data['examination_type'] ?? $stream->examination_type,
                    'sort_order' => $data['sort_order'] ?? $stream->sort_order,
                    'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $stream->is_active,
                    'default_subjects' => $data['default_subjects'] ?? $stream->default_subjects,
                ]
            );

            return back()->with('success', 'Sahodaya override saved for global stream.');
        }

        $data = $request->validate([
            'label' => 'required|string|max:120',
            'examination_type' => 'nullable|string|max:40',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'default_subjects' => 'nullable|array',
            'default_subjects.*' => 'string|max:120',
        ]);

        $stream->update([
            'label' => $data['label'],
            'examination_type' => $data['examination_type'] ?? $stream->examination_type,
            'sort_order' => $data['sort_order'] ?? $stream->sort_order,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $stream->is_active,
            'default_subjects' => $data['default_subjects'] ?? $stream->default_subjects,
        ]);

        return back()->with('success', 'Exam stream updated.');
    }

    public function destroyStream(string $tenantId, ExamStream $stream)
    {
        abort_if($stream->sahodaya_id !== $this->sahodaya->id, 403, 'Global streams cannot be deleted here.');

        if (Topper::query()->where('stream_id', $stream->id)->exists()
            || TopperCountConfig::query()->where('stream_id', $stream->id)->exists()) {
            $stream->update(['is_active' => false]);

            return back()->with('success', 'Stream deactivated (still referenced by toppers/config).');
        }

        $stream->delete();

        return back()->with('success', 'Exam stream removed.');
    }

    public function updateApiConfig(Request $request)
    {
        $data = $request->validate([
            'weight_pass_percent' => 'required|numeric|min:0|max:100',
            'weight_distinctions' => 'required|numeric|min:0|max:100',
            'weight_highest_mark' => 'required|numeric|min:0|max:100',
            'weight_toppers' => 'required|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $sum = (float) $data['weight_pass_percent']
            + (float) $data['weight_distinctions']
            + (float) $data['weight_highest_mark']
            + (float) $data['weight_toppers'];

        if (abs($sum - 100) > 0.01) {
            return back()->withErrors([
                'weight_pass_percent' => 'API weights must sum to 100 (currently '.$sum.').',
            ]);
        }

        $config = ApiConfig::forSahodaya($this->sahodaya->id);
        $config->update([
            'weight_pass_percent' => $data['weight_pass_percent'],
            'weight_distinctions' => $data['weight_distinctions'],
            'weight_highest_mark' => $data['weight_highest_mark'],
            'weight_toppers' => $data['weight_toppers'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with('success', 'Academic Performance Index weights saved.');
    }
}
