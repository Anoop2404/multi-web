<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\McqExam;

class McqExamActivityService
{
    /** @return list<array<string, mixed>> */
    public function forExam(McqExam $exam, int $limit = 100): array
    {
        $morph = (new McqExam)->getMorphClass();
        $examId = (string) $exam->id;

        return AuditLog::query()
            ->with('user:id,name,email')
            ->where(function ($q) use ($morph, $examId, $exam) {
                $q->where(function ($q2) use ($morph, $examId) {
                    $q2->where('subject_type', $morph)->where('subject_id', $examId);
                })->orWhere('properties->exam_id', $exam->id);
            })
            ->where('category', 'mcq')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id'          => $log->id,
                'action'      => $log->action,
                'description' => $log->description,
                'user'        => $log->user?->only('id', 'name', 'email'),
                'created_at'  => $log->created_at?->toDateTimeString(),
            ])
            ->values()
            ->all();
    }
}
