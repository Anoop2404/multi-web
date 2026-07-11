<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\BoardResult;
use App\Models\Tenant;
use App\Models\TopperCountConfig;
use App\Services\Audit\DataChangeLogger;
use App\Services\BoardResults\BoardResultNotifier;
use App\Services\BoardResults\BoardResultPublishPipeline;
use App\Services\BoardResults\TopperCountService;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BoardResultVerificationController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: 'submitted';
        $schoolIds = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->pluck('id');

        $results = BoardResult::query()
            ->whereIn('tenant_id', $schoolIds)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['toppers', 'uploads' => fn ($q) => $q->where('file_type', 'pdf')->orderByDesc('version')->limit(3)])
            ->orderByDesc('submitted_at')
            ->orderByDesc('updated_at')
            ->paginate(25)
            ->withQueryString();

        $schoolNames = Tenant::whereIn('id', $results->pluck('tenant_id')->unique())
            ->pluck('name', 'id');

        $topperConfigs = TopperCountConfig::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->orderBy('class')
            ->get();

        return $this->inertia('Sahodaya/BoardResults/Verification', [
            'results' => $results,
            'schoolNames' => $schoolNames,
            'filters' => ['status' => $status],
            'statusOptions' => [
                'submitted' => 'Submitted',
                'verified' => 'Verified',
                'approved' => 'Approved',
                'published' => 'Published',
                'rejected' => 'Rejected',
                'draft' => 'Draft',
                'all' => 'All',
            ],
            'topperConfigs' => $topperConfigs,
            'defaultTopN' => TopperCountService::DEFAULT_TOP_N,
        ]);
    }

    public function updateTopperCap(Request $request)
    {
        $data = $request->validate([
            'class' => 'nullable|integer|in:10,12',
            'scope' => 'nullable|string|in:overall,stream,subject',
            'top_n' => 'required|integer|min:1|max:50',
            'stream_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
        ]);

        $config = app(TopperCountService::class)->upsert($this->sahodaya->id, $data);

        return back()->with('success', "Top-N set to {$config->top_n}.");
    }

    public function verify(Request $request, BoardResult $boardResult)
    {
        $this->assertInScope($boardResult);
        abort_unless($boardResult->status === BoardResult::STATUS_SUBMITTED, 422, 'Only submitted results can be verified.');

        $boardResult->update([
            'status' => BoardResult::STATUS_VERIFIED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        app(DataChangeLogger::class)->event(
            'verified',
            'Board result verified',
            $boardResult->tenant_id,
            'board_result',
            $boardResult,
        );

        return back()->with('success', 'Board result marked verified.');
    }

    public function approve(Request $request, BoardResult $boardResult)
    {
        $this->assertInScope($boardResult);
        abort_unless(
            in_array($boardResult->status, [BoardResult::STATUS_SUBMITTED, BoardResult::STATUS_VERIFIED], true),
            422,
            'Only submitted or verified results can be approved.'
        );

        $boardResult->update([
            'status' => BoardResult::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'verified_by' => $boardResult->verified_by ?? $request->user()->id,
            'verified_at' => $boardResult->verified_at ?? now(),
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        app(DataChangeLogger::class)->event(
            'approved',
            'Board result approved',
            $boardResult->tenant_id,
            'board_result',
            $boardResult,
        );

        app(BoardResultNotifier::class)->approved($boardResult);

        return back()->with('success', 'Board result approved.');
    }

    public function reject(Request $request, BoardResult $boardResult)
    {
        $this->assertInScope($boardResult);
        abort_unless(
            in_array($boardResult->status, [
                BoardResult::STATUS_SUBMITTED,
                BoardResult::STATUS_VERIFIED,
                BoardResult::STATUS_APPROVED,
            ], true),
            422,
            'This result cannot be rejected in its current status.'
        );

        $data = $request->validate([
            'rejection_reason' => 'required|string|max:2000',
        ]);

        $history = $boardResult->correction_history ?? [];
        $history[] = [
            'at' => now()->toIso8601String(),
            'by' => $request->user()->id,
            'action' => 'rejected',
            'reason' => $data['rejection_reason'],
            'from_status' => $boardResult->status,
            'submission_count' => (int) ($boardResult->submission_count ?? 0),
            'pdf_path' => $boardResult->result_pdf_path,
        ];

        $boardResult->update([
            'status' => BoardResult::STATUS_REJECTED,
            'rejection_reason' => $data['rejection_reason'],
            'correction_history' => $history,
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
            'published_at' => null,
        ]);

        app(DataChangeLogger::class)->event(
            'rejected',
            'Board result rejected',
            $boardResult->tenant_id,
            'board_result',
            $boardResult,
            ['reason' => $data['rejection_reason']],
        );

        app(BoardResultNotifier::class)->rejected($boardResult);

        return back()->with('success', 'Board result rejected and school notified.');
    }

    public function publish(Request $request, BoardResult $boardResult, BoardResultPublishPipeline $pipeline)
    {
        $this->assertInScope($boardResult);
        abort_unless(
            in_array($boardResult->status, [BoardResult::STATUS_APPROVED, BoardResult::STATUS_VERIFIED], true),
            422,
            'Approve the result before publishing.'
        );

        if (! $boardResult->hasResultPdf()) {
            throw ValidationException::withMessages([
                'result_pdf' => 'Cannot publish without a CBSE result PDF on file.',
            ]);
        }

        $boardResult->update([
            'status' => BoardResult::STATUS_PUBLISHED,
            'published_at' => now(),
            'approved_by' => $boardResult->approved_by ?? $request->user()->id,
            'approved_at' => $boardResult->approved_at ?? now(),
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $pipeline->run($this->sahodaya->id, $boardResult->academic_year, $boardResult);

        app(DataChangeLogger::class)->event(
            'published',
            'Board result published (ranking + API + awards + topper certificates)',
            $boardResult->tenant_id,
            'board_result',
            $boardResult,
        );

        app(BoardResultNotifier::class)->published($boardResult);

        return back()->with('success', 'Board result published; rankings, API scores, awards, and topper certificates updated.');
    }

    public function downloadPdf(BoardResult $boardResult)
    {
        $this->assertInScope($boardResult);
        abort_unless($boardResult->hasResultPdf(), 404);

        $upload = $boardResult->uploads()->where('file_type', 'pdf')->orderByDesc('version')->first();

        return TenantStorage::downloadPrivate(
            $boardResult->result_pdf_path,
            $boardResult->result_pdf_disk ?? $upload?->storage_disk,
            $upload?->file_name ?? 'board-result.pdf'
        );
    }

    private function assertInScope(BoardResult $boardResult): void
    {
        $school = Tenant::find($boardResult->tenant_id);
        abort_unless($school && $school->parent_id === $this->sahodaya->id, 404);
    }
}
