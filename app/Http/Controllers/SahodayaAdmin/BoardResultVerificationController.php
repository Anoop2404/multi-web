<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\BoardResult;
use App\Models\BoardResultCertificationPackage;
use App\Models\Tenant;
use App\Models\Topper;
use App\Services\Audit\DataChangeLogger;
use App\Services\BoardResults\BoardResultCertificationService;
use App\Services\BoardResults\BoardResultNotifier;
use App\Services\BoardResults\BoardResultPublishPipeline;
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
            ->with([
                'uploads' => fn ($q) => $q->orderByDesc('version')->limit(5),
                'certificationPackages',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('updated_at')
            ->paginate(25)
            ->withQueryString();

        $schoolNames = Tenant::whereIn('id', $results->pluck('tenant_id')->unique())
            ->pluck('name', 'id');

        $results->getCollection()->transform(function (BoardResult $result) {
            $result->setAttribute('latest_proof_label', $this->proofLabelForResult($result));
            $result->setAttribute('latest_proof_type', $this->proofTypeForResult($result));
            $result->setAttribute('latest_proof_url', $this->proofUrlForResult($result));
            $result->setAttribute('certification_package', $result->activeCertificationPackage());

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
            'selectedClass' => $class,
        ]);
    }

    public function exportSchoolProofs(Request $request)
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
            ->orderByDesc('submitted_at')
            ->orderByDesc('updated_at')
            ->get();

        $schoolNames = Tenant::whereIn('id', $results->pluck('tenant_id')->unique())
            ->pluck('name', 'id');

        $rows = $results->map(fn (BoardResult $result) => [
            $schoolNames[$result->tenant_id] ?? $result->tenant_id,
            $result->class,
            $result->academic_year,
            $result->status,
            $result->submitted_at?->format('Y-m-d H:i'),
            $result->rejection_reason,
        ]);

        return \App\Support\ExcelExport::download(
            'board-result-school-proofs-'.$status,
            ['School', 'Class', 'Academic Year', 'Status', 'Submitted At', 'Rejection Reason'],
            $rows,
        );
    }

    public function verifyOverall(Request $request)
    {
        return $this->renderTopperVerificationPage($request, Topper::ENTRY_OVERALL, 'VerifyOverallToppers');
    }

    public function exportOverall(Request $request)
    {
        return $this->exportTopperVerification($request, Topper::ENTRY_OVERALL, 'overall-toppers');
    }

    public function verifySubjects(Request $request)
    {
        return $this->renderTopperVerificationPage($request, Topper::ENTRY_SUBJECT, 'VerifySubjectToppers');
    }

    public function exportSubjects(Request $request)
    {
        return $this->exportTopperVerification($request, Topper::ENTRY_SUBJECT, 'subject-toppers');
    }

    public function verifyA1(Request $request)
    {
        return $this->renderTopperVerificationPage($request, Topper::ENTRY_FULL_A1, 'VerifyA1Achievers');
    }

    public function exportA1(Request $request)
    {
        return $this->exportTopperVerification($request, Topper::ENTRY_FULL_A1, 'full-a1-achievers');
    }

    /** Shared by the on-screen page and the Excel export below, so they can never drift. */
    private function topperVerificationQuery(Request $request, string $entryType): array
    {
        $status = $request->string('status')->toString() ?: 'pending';
        $class = $request->filled('class') ? $request->integer('class') : 10;
        abort_if(! in_array($class, [10, 12], true), 404);

        $schoolIds = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->pluck('id');

        $query = Topper::query()
            ->with(['boardResult', 'examStream', 'subjectMarks'])
            ->whereHas('boardResult', function ($q) use ($schoolIds, $class) {
                $q->whereIn('tenant_id', $schoolIds)
                  ->where('class', $class);
            })
            ->where('entry_type', $entryType)
            ->when($status !== 'all', function ($q) use ($status) {
                if ($status === 'pending') {
                    $q->where('verification_status', 'pending');
                } else {
                    $q->where('verification_status', $status);
                }
            })
            ->orderBy('created_at', 'desc');

        return [$query, $status, $class];
    }

    private function renderTopperVerificationPage(Request $request, string $entryType, string $component)
    {
        [$query, $status, $class] = $this->topperVerificationQuery($request, $entryType);

        $toppers = $query->paginate(50)->withQueryString();

        $schoolNames = Tenant::whereIn('id', $toppers->pluck('tenant_id')->unique())
            ->pluck('name', 'id');

        return $this->inertia('Sahodaya/BoardResults/' . $component, [
            'toppers' => $toppers,
            'schoolNames' => $schoolNames,
            'filters' => ['status' => $status, 'class' => $class],
            'selectedClass' => $class,
            'statusOptions' => [
                'pending' => 'Pending Verification',
                'verified' => 'Verified',
                'rejected' => 'Rejected',
                'all' => 'All',
            ],
        ]);
    }

    private function exportTopperVerification(Request $request, string $entryType, string $slug)
    {
        [$query, $status, $class] = $this->topperVerificationQuery($request, $entryType);

        $toppers = $query->get();

        $schoolNames = Tenant::whereIn('id', $toppers->pluck('tenant_id')->unique())
            ->pluck('name', 'id');

        $headers = ['School', 'Student', 'Admission No', 'Roll No', 'Stream', 'Subjects & Marks', 'Percentage', 'Verification Status', 'Rejection Reason'];

        $rowFor = function (Topper $topper) use ($schoolNames) {
            $subjects = collect($topper->subject_marks)
                ->map(fn ($marks, $subject) => "{$subject}: {$marks}")
                ->implode(', ');

            return [
                $schoolNames[$topper->tenant_id] ?? $topper->tenant_id,
                $topper->name,
                $topper->admission_no,
                $topper->roll_no,
                $topper->examStream?->name ?? $topper->stream,
                $subjects,
                $topper->percentage,
                $topper->verification_status,
                $topper->rejection_reason,
            ];
        };

        $filename = "{$slug}-class-{$class}-{$status}";

        // Stream Toppers (Class XII) — one sheet per stream (Commerce, Science, ...),
        // ranked by percentage within each so the sheet reads top-to-bottom like a
        // leaderboard. Class X has no streams, so this collapses to a single
        // "No Stream" sheet there — same shape, just not worth a separate branch.
        if ($entryType === Topper::ENTRY_OVERALL) {
            $sheets = [];
            foreach ($toppers->groupBy(fn (Topper $t) => $t->examStream?->name ?? $t->stream ?: 'No Stream')->sortKeys() as $streamName => $streamToppers) {
                $sheets[$streamName] = [
                    'headers' => $headers,
                    'rows' => $streamToppers->sortByDesc('percentage')->map($rowFor)->values(),
                ];
            }

            return \App\Support\ExcelExport::downloadMultiSheet($filename, $sheets);
        }

        // Subject Toppers — one sheet per subject, ranked by that subject's marks
        // (top mark first) within each sheet. A subject-entry Topper's subject_marks
        // map always holds exactly one {subject: marks} pair (see Topper::saving()'s
        // entry_type !== ENTRY_SUBJECT guard on percentage — subject entries don't get
        // an overall percentage at all, so marks is the only meaningful rank order).
        if ($entryType === Topper::ENTRY_SUBJECT) {
            $sheets = [];
            $bySubject = $toppers->groupBy(function (Topper $t) {
                $subjects = $t->subject_marks;

                return $subjects === [] ? 'Subject' : array_key_first($subjects);
            });

            foreach ($bySubject->sortKeys() as $subjectName => $subjectToppers) {
                $sorted = $subjectToppers->sortByDesc(function (Topper $t) {
                    $marks = $t->subject_marks;

                    return $marks === [] ? 0 : reset($marks);
                })->values();

                $sheets[$subjectName] = [
                    'headers' => $headers,
                    'rows' => $sorted->map($rowFor)->values(),
                ];
            }

            return \App\Support\ExcelExport::downloadMultiSheet($filename, $sheets);
        }

        // Full A1 Achievers — one row per (student, subject) pair instead of a single
        // "Subjects & Marks" cell cramming every subject together, since a student can
        // hold several A1s and a reviewer needs each one as its own sortable/filterable row.
        if ($entryType === Topper::ENTRY_FULL_A1) {
            $a1Headers = ['School', 'Student', 'Admission No', 'Roll No', 'Stream', 'Subject', 'Marks', 'Percentage', 'Verification Status', 'Rejection Reason'];

            $rows = $toppers->flatMap(function (Topper $topper) use ($schoolNames) {
                $subjects = $topper->subject_marks;
                if ($subjects === []) {
                    $subjects = ['—' => null];
                }

                return collect($subjects)->map(fn ($marks, $subject) => [
                    $schoolNames[$topper->tenant_id] ?? $topper->tenant_id,
                    $topper->name,
                    $topper->admission_no,
                    $topper->roll_no,
                    $topper->examStream?->name ?? $topper->stream,
                    $subject,
                    $marks,
                    $topper->percentage,
                    $topper->verification_status,
                    $topper->rejection_reason,
                ]);
            });

            return \App\Support\ExcelExport::download($filename, $a1Headers, $rows);
        }

        return \App\Support\ExcelExport::download($filename, $headers, $toppers->map($rowFor));
    }

    public function verify(Request $request, string $tenantId, BoardResult $boardResult)
    {
        $this->assertInScope($boardResult);

        // A school that has started Principal Verification for this result must fully
        // complete it (every individual report + the consolidated report signed and
        // submitted) before Sahodaya can verify — plan §9. Results that never went
        // through the new workflow (no package at all) are unaffected.
        $package = $boardResult->activeCertificationPackage();
        if ($package) {
            abort_unless(
                $package->status === BoardResultCertificationPackage::STATUS_SUBMITTED_TO_SAHODAYA,
                422,
                'This result has not completed school certification (Principal Verification) yet.'
            );
        }

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

        if ($package) {
            app(BoardResultCertificationService::class)->sahodayaVerify($package, $request->user());
            try {
                app(\App\Services\BoardResults\BoardResultCertificationNotifier::class)->sahodayaVerified($package->fresh());
            } catch (\Throwable) {
                // Notifications must never block workflow transitions.
            }
        }

        return back()->with('success', 'Board result marked verified.');
    }

    public function approve(Request $request, string $tenantId, BoardResult $boardResult)
    {
        $this->assertInScope($boardResult);

        $package = $boardResult->activeCertificationPackage();
        if ($package) {
            abort_unless(
                $package->status === BoardResultCertificationPackage::STATUS_SAHODAYA_VERIFIED,
                422,
                'Verify the certified package before approving it.'
            );
        }

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

        if ($package) {
            app(BoardResultCertificationService::class)->sahodayaApprove($package, $request->user());
            try {
                app(\App\Services\BoardResults\BoardResultCertificationNotifier::class)->sahodayaApproved($package->fresh());
            } catch (\Throwable) {
                // Notifications must never block workflow transitions.
            }
        }

        return back()->with('success', 'Board result approved.');
    }

    public function reject(Request $request, string $tenantId, BoardResult $boardResult)
    {
        $this->assertInScope($boardResult);

        $data = $request->validate([
            'rejection_reason' => 'required|string|max:2000',
        ]);

        $package = $boardResult->activeCertificationPackage();
        if ($package && in_array($package->status, [
            BoardResultCertificationPackage::STATUS_SUBMITTED_TO_SAHODAYA,
            BoardResultCertificationPackage::STATUS_SAHODAYA_VERIFIED,
            BoardResultCertificationPackage::STATUS_APPROVED,
        ], true)) {
            // sahodayaReturn() mutates $package in place (old version -> superseded, with
            // return_reason set) before returning the newly spawned draft version, so
            // $package itself (not the return value) is what the notifier should read.
            app(BoardResultCertificationService::class)->sahodayaReturn($package, $request->user(), $data['rejection_reason']);
            try {
                app(\App\Services\BoardResults\BoardResultCertificationNotifier::class)->sahodayaReturned($package);
            } catch (\Throwable) {
                // Notifications must never block workflow transitions.
            }
        }

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

    public function publish(Request $request, string $tenantId, BoardResult $boardResult, BoardResultPublishPipeline $pipeline)
    {
        $this->assertInScope($boardResult);

        $package = $boardResult->activeCertificationPackage();
        if ($package) {
            abort_unless(
                $package->status === BoardResultCertificationPackage::STATUS_APPROVED,
                422,
                'Approve the certified package before publishing it.'
            );
        }

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

        if ($package) {
            try {
                app(BoardResultCertificationService::class)->sahodayaPublish($package, $request->user());
                app(\App\Services\BoardResults\BoardResultCertificationNotifier::class)->sahodayaPublished($package->fresh());
            } catch (\Throwable $e) {
                logger()->error('Failed to sync certification package status after publish: '.$e->getMessage(), [
                    'board_result_id' => $boardResult->id,
                    'package_id' => $package->id,
                ]);
            }
        }

        return back()->with('success', 'Board result published.');
    }

    /**
     * Reopen a published result for correction. Deliberately narrow in scope, matching
     * FestResultsController::unpublish()'s precedent: flips the status back and lets the
     * school resubmit through the normal review chain — it does not reverse the publish
     * pipeline's ranking/API/award recompute (use the Toppers hub's "Recalculate rankings"
     * action afterward) and does not revoke certificates already issued to toppers, which
     * is a materially different, larger feature.
     *
     * Targets Rejected, not Approved — BoardResult::isEditable() only allows draft/rejected/
     * (unreviewed) submitted, so landing on Approved would reopen the result in name only
     * and leave the school unable to actually fix anything. Rejected reuses the school's
     * existing, working "sent back for correction" screen instead of inventing a new one.
     */
    public function unpublish(Request $request, string $tenantId, BoardResult $boardResult)
    {
        $this->assertInScope($boardResult);

        $data = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $package = $boardResult->activeCertificationPackage();
        if ($package) {
            abort_unless(
                $package->status === BoardResultCertificationPackage::STATUS_PUBLISHED,
                422,
                'This result\'s certification package is not published.'
            );
        }

        DB::transaction(function () use ($request, $boardResult, $data) {
            $locked = BoardResult::query()->whereKey($boardResult->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === BoardResult::STATUS_PUBLISHED, 422, 'Only published results can be unpublished.');

            $reason = 'Unpublished for correction: '.$data['reason'];

            $history = $locked->correction_history ?? [];
            $history[] = [
                'at' => now()->toIso8601String(),
                'by' => $request->user()->id,
                'action' => 'unpublished',
                'reason' => $data['reason'],
                'from_status' => $locked->status,
                'submission_count' => (int) ($locked->submission_count ?? 0),
                'pdf_path' => $locked->result_pdf_path,
            ];

            $locked->update([
                'status' => BoardResult::STATUS_REJECTED,
                'rejection_reason' => $reason,
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
                'unpublished',
                'Board result unpublished for correction',
                $locked->tenant_id,
                'board_result',
                $locked,
                ['reason' => $data['reason']],
            );

            try {
                app(BoardResultNotifier::class)->rejected($locked->fresh());
            } catch (\Throwable) {
                // Notifications must never block workflow transitions.
            }
        });

        if ($package) {
            app(BoardResultCertificationService::class)->unpublish($package, $request->user(), $data['reason']);
        }

        return back()->with('success', 'Board result unpublished and sent back to the school for correction. Rankings will look stale until you recalculate them from the Toppers hub.');
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

        $package = $boardResult->activeCertificationPackage();
        if ($package) {
            abort_unless(
                $package->status === BoardResultCertificationPackage::STATUS_SUBMITTED_TO_SAHODAYA,
                422,
                'This result has not completed school certification (Principal Verification) yet.'
            );
        }

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

        if ($package) {
            app(BoardResultCertificationService::class)->sahodayaVerify($package, $request->user());
        }

        return back()->with('success', 'Board result and all student achievers marked as verified.');
    }
}
