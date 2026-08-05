<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\BoardResult;
use App\Models\Tenant;
use App\Models\Topper;
use App\Models\TopperCountConfig;
use App\Services\Audit\DataChangeLogger;
use App\Services\BoardResults\BoardResultNotifier;
use App\Services\BoardResults\BoardResultPublishPipeline;
use App\Services\BoardResults\TopperCountService;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BoardResultVerificationController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: 'submitted';
        $class = $request->filled('class') ? $request->integer('class') : null;
        abort_if($class !== null && ! in_array($class, [10, 12], true), 404);

        $schoolIds = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->pluck('id');

        $results = BoardResult::query()
            ->whereIn('tenant_id', $schoolIds)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($class, fn ($q) => $q->where('class', $class))
            ->with(['toppers', 'uploads' => fn ($q) => $q->orderByDesc('version')->limit(5)])
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

        $results->getCollection()->transform(function (BoardResult $result) {
            $result->setAttribute('latest_proof_label', $this->proofLabelForResult($result));
            $result->setAttribute('latest_proof_type', $this->proofTypeForResult($result));
            $result->setAttribute('latest_proof_url', $this->proofUrlForResult($result));

            return $result;
        });

        return $this->inertia('Sahodaya/BoardResults/Verification', [
            'results' => $results,
            'schoolNames' => $schoolNames,
            'filters' => ['status' => $status, 'class' => $class],
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
            'selectedClass' => $class,
        ]);
    }

    public function updateTopperCap(Request $request)
    {
        $data = $request->validate([
            'class' => 'nullable|integer|in:10,12',
            'scope' => 'nullable|string|in:overall,stream,subject',
            'top_n' => 'required|integer|min:1|max:50',
            'tie_mode' => 'nullable|string|in:include_group,hard_cap',
            'rank_style' => 'nullable|string|in:competition,dense,sequential',
            'stream_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
        ]);

        $config = app(TopperCountService::class)->upsert($this->sahodaya->id, $data);

        return back()->with('success', "Top-N set to {$config->top_n}.");
    }

    public function verify(Request $request, BoardResult $boardResult)
    {
        $this->assertInScope($boardResult);

        DB::transaction(function () use ($request, $boardResult) {
            $locked = BoardResult::query()->whereKey($boardResult->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === BoardResult::STATUS_SUBMITTED, 422, 'Only submitted results can be verified.');

            $locked->update([
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
                $locked->tenant_id,
                'board_result',
                $locked,
            );
        });

        return back()->with('success', 'Board result marked verified.');
    }

    public function approve(Request $request, BoardResult $boardResult)
    {
        $this->assertInScope($boardResult);

        DB::transaction(function () use ($request, $boardResult) {
            $locked = BoardResult::query()->whereKey($boardResult->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $locked->status === BoardResult::STATUS_VERIFIED,
                422,
                'Verify the result before approving.'
            );

            $locked->update([
                'status' => BoardResult::STATUS_APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            app(DataChangeLogger::class)->event(
                'approved',
                'Board result approved',
                $locked->tenant_id,
                'board_result',
                $locked,
            );

            try {
                app(BoardResultNotifier::class)->approved($locked->fresh());
            } catch (\Throwable) {
                // Notifications must never block workflow transitions.
            }
        });

        return back()->with('success', 'Board result approved.');
    }

    public function reject(Request $request, BoardResult $boardResult)
    {
        $this->assertInScope($boardResult);

        $data = $request->validate([
            'rejection_reason' => 'required|string|max:2000',
        ]);

        DB::transaction(function () use ($request, $boardResult, $data) {
            $locked = BoardResult::query()->whereKey($boardResult->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                in_array($locked->status, [
                    BoardResult::STATUS_SUBMITTED,
                    BoardResult::STATUS_VERIFIED,
                    BoardResult::STATUS_APPROVED,
                ], true),
                422,
                'This result cannot be rejected in its current status.'
            );

            $history = $locked->correction_history ?? [];
            $history[] = [
                'at' => now()->toIso8601String(),
                'by' => $request->user()->id,
                'action' => 'rejected',
                'reason' => $data['rejection_reason'],
                'from_status' => $locked->status,
                'submission_count' => (int) ($locked->submission_count ?? 0),
                'pdf_path' => $locked->result_pdf_path,
            ];

            $locked->update([
                'status' => BoardResult::STATUS_REJECTED,
                'rejection_reason' => $data['rejection_reason'],
                'correction_history' => $history,
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(),
                'published_at' => null,
                'verified_by' => null,
                'verified_at' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            app(DataChangeLogger::class)->event(
                'rejected',
                'Board result rejected',
                $locked->tenant_id,
                'board_result',
                $locked,
                ['reason' => $data['rejection_reason']],
            );

            try {
                app(BoardResultNotifier::class)->rejected($locked->fresh());
            } catch (\Throwable) {
                // Notifications must never block workflow transitions.
            }
        });

        return back()->with('success', 'Board result rejected and school notified.');
    }

    public function publish(Request $request, BoardResult $boardResult, BoardResultPublishPipeline $pipeline)
    {
        $this->assertInScope($boardResult);

        // First: validate and save the status update in a short locked transaction.
        $academicYear = DB::transaction(function () use ($request, $boardResult) {
            $locked = BoardResult::query()->whereKey($boardResult->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $locked->status === BoardResult::STATUS_APPROVED,
                422,
                'Approve the result before publishing.'
            );

            if (! $locked->hasResultPdf()) {
                throw ValidationException::withMessages([
                    'result_pdf' => 'Cannot publish without a proof document on file.',
                ]);
            }

            $locked->update([
                'status' => BoardResult::STATUS_PUBLISHED,
                'published_at' => now(),
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            return $locked->academic_year;
        });

        // Then: run the heavy post-publish computation outside the locked transaction
        // so ranking, awards, certificates don't block concurrent operations on the result.
        try {
            $result = $pipeline->run($this->sahodaya->id, $academicYear, $boardResult->fresh());

            app(DataChangeLogger::class)->event(
                'published',
                'Board result published (ranking + API + awards + topper certificates)',
                $boardResult->tenant_id,
                'board_result',
                $boardResult,
            );

            try {
                app(BoardResultNotifier::class)->published($boardResult->fresh());
            } catch (\Throwable) {
                // Notifications must never block workflow transitions.
            }
        } catch (\Throwable $e) {
            // Pipeline failure (ranking, awards, certificates) should not undo the publish.
            // Log it so it can be investigated and retried.
            logger()->error('Board result publish pipeline failed after status update: '.$e->getMessage(), [
                'board_result_id' => $boardResult->id,
                'sahodaya_id' => $this->sahodaya->id,
                'academic_year' => $academicYear,
            ]);
        }

        return back()->with('success', 'Board result published.');
    }

    public function downloadPdf(Request $request, string $tenantId, string $boardResult)
    {
        // Every sibling action on this controller (verify/approve/reject/publish) uses
        // implicit BoardResult $boardResult binding successfully — this route is the one
        // reported failing in production with "Argument #2 ($boardResult) must be of type
        // App\Models\BoardResult, string given", even though the binding query itself runs
        // and finds the row (confirmed from the request's query log). Rather than leave a
        // live PDF download endpoint broken while that framework-level quirk gets tracked
        // down, resolve the model explicitly here instead of relying on implicit binding.
        $boardResult = BoardResult::findOrFail($boardResult);
        $this->assertInScope($boardResult);

        $version = $request->integer('version') ?: null;
        $uploadQuery = $boardResult->uploads()->where('file_type', 'pdf');
        $upload = $version
            ? (clone $uploadQuery)->where('version', $version)->first()
            : (clone $uploadQuery)->orderByDesc('version')->first();

        $path = $upload?->file_path ?? $boardResult->result_pdf_path;
        abort_unless($path, 404);

        return TenantStorage::downloadPrivate(
            $path,
            $boardResult->result_pdf_disk ?? $upload?->storage_disk,
            $request->boolean('preview') ? null : ($upload?->file_name ?? basename($path))
        );
    }

    private function proofLabelForResult(BoardResult $result): string
    {
        $upload = $result->uploads->first();

        if ($upload?->file_name) {
            return $upload->file_name;
        }

        return basename((string) $result->result_pdf_path ?: 'proof-file');
    }

    private function proofTypeForResult(BoardResult $result): string
    {
        $upload = $result->uploads->first();
        if ($upload?->file_type) {
            return $upload->file_type;
        }

        return $this->guessProofType($upload?->file_name ?? $result->result_pdf_path);
    }

    private function proofUrlForResult(BoardResult $result): ?string
    {
        if (! $result->result_pdf_path && $result->uploads->isEmpty()) {
            return null;
        }

        return "/sahodaya-admin/{$this->sahodaya->id}/board-results/{$result->id}/pdf?preview=1";
    }

    private function guessProofType(?string $path): string
    {
        $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

        return match (true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) => 'image',
            $ext === 'pdf' => 'pdf',
            in_array($ext, ['doc', 'docx', 'xls', 'xlsx'], true) => 'document',
            default => 'file',
        };
    }

    private function assertInScope(BoardResult $boardResult): void
    {
        $school = Tenant::find($boardResult->tenant_id);
        abort_unless($school && $school->parent_id === $this->sahodaya->id, 404);
    }

    public function verifyTopperMarksheet(Request $request, string $tenantId, BoardResult $boardResult, Topper $topper)
    {
        $this->assertInScope($boardResult);
        abort_if($topper->board_result_id !== $boardResult->id, 404);

        $topper->update([
            'verification_status' => 'verified',
            'verified_at'          => now(),
            'verified_by'          => auth()->user()?->name ?? 'Sahodaya Admin',
            'rejection_reason'     => null,
        ]);

        return back()->with('success', "Marksheet for {$topper->name} marked as verified.");
    }

    public function rejectTopperMarksheet(Request $request, string $tenantId, BoardResult $boardResult, Topper $topper)
    {
        $this->assertInScope($boardResult);
        abort_if($topper->board_result_id !== $boardResult->id, 404);

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $topper->update([
            'verification_status' => 'rejected',
            'rejection_reason'     => $data['reason'] ?? 'Marksheet verification failed.',
            'verified_at'          => now(),
            'verified_by'          => auth()->user()?->name ?? 'Sahodaya Admin',
        ]);

        return back()->with('success', "Marksheet for {$topper->name} marked as rejected.");
    }

    public function verifyAllA1Achievers(Request $request, string $tenantId, BoardResult $boardResult)
    {
        $this->assertInScope($boardResult);

        $count = $boardResult->toppers()
            ->where('entry_type', Topper::ENTRY_FULL_A1)
            ->update([
                'verification_status' => 'verified',
                'rejection_reason'     => null,
                'verified_at'          => now(),
                'verified_by'          => auth()->user()?->name ?? 'Sahodaya Admin',
            ]);

        return back()->with('success', "Verified {$count} Full A1 Achievers.");
    }

    public function verifyAllToppers(Request $request, string $tenantId, BoardResult $boardResult)
    {
        $this->assertInScope($boardResult);

        $count = $boardResult->toppers()
            ->whereIn('entry_type', [Topper::ENTRY_OVERALL, Topper::ENTRY_SUBJECT])
            ->update([
                'verification_status' => 'verified',
                'rejection_reason'     => null,
                'verified_at'          => now(),
                'verified_by'          => auth()->user()?->name ?? 'Sahodaya Admin',
            ]);

        return back()->with('success', "Verified {$count} toppers.");
    }

    public function verifyAll(Request $request, string $tenantId, BoardResult $boardResult)
    {
        $this->assertInScope($boardResult);

        DB::transaction(function () use ($request, $boardResult) {
            $locked = BoardResult::query()->whereKey($boardResult->id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'status' => BoardResult::STATUS_VERIFIED,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $locked->toppers()->update([
                'verification_status' => 'verified',
                'rejection_reason'     => null,
                'verified_at'          => now(),
                'verified_by'          => auth()->user()?->name ?? 'Sahodaya Admin',
            ]);
        });

        return back()->with('success', 'Board result and all student achievers marked as verified.');
    }
}
