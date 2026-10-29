<?php

namespace App\Services\Mcq;

use App\Models\McqQuestionBank;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Copy association banks into a school's private bank, or contribute a school bank
 * as a Sahodaya-owned copy (HOD contribution without mutating the source).
 */
class McqQuestionBankCopyService
{
    public function __construct(private PlatformAuditLogger $audit) {}

    /** Deep-copy a Sahodaya (or any Ready) bank into the school's private banks. */
    public function copyIntoSchool(McqQuestionBank $source, Tenant $school, int $userId): McqQuestionBank
    {
        abort_unless($school->type === 'school', 422);
        abort_unless($school->parent_id === $source->sahodaya_id, 403);

        if ($source->status !== 'active') {
            throw ValidationException::withMessages([
                'bank' => 'Only Ready banks can be copied into your school library.',
            ]);
        }

        return $this->cloneBank(
            source: $source,
            schoolId: $school->id,
            contributedBySchoolId: null,
            title: $source->title.' (school copy)',
            userId: $userId,
            auditEvent: 'mcq.question_bank.copied_to_school',
            auditMessage: "Copied bank \"{$source->title}\" into school {$school->name}",
        );
    }

    /** Contribute a school Ready bank as a new Sahodaya-owned association bank. */
    public function contributeToSahodaya(McqQuestionBank $source, Tenant $school, int $userId): McqQuestionBank
    {
        abort_unless($school->type === 'school', 422);
        abort_unless($source->school_id === $school->id, 403);
        abort_unless($source->sahodaya_id === $school->parent_id, 403);

        if ($source->status !== 'active') {
            throw ValidationException::withMessages([
                'bank' => 'Mark the bank Ready before contributing a copy to Sahodaya.',
            ]);
        }

        return $this->cloneBank(
            source: $source,
            schoolId: null,
            contributedBySchoolId: $school->id,
            title: $source->title.' (from '.$school->name.')',
            userId: $userId,
            auditEvent: 'mcq.question_bank.contributed',
            auditMessage: "School {$school->name} contributed a copy of \"{$source->title}\" to Sahodaya",
        );
    }

    private function cloneBank(
        McqQuestionBank $source,
        ?string $schoolId,
        ?string $contributedBySchoolId,
        string $title,
        int $userId,
        string $auditEvent,
        string $auditMessage,
    ): McqQuestionBank {
        return DB::transaction(function () use ($source, $schoolId, $contributedBySchoolId, $title, $userId, $auditEvent, $auditMessage) {
            $source->load('questions');

            $copy = McqQuestionBank::create([
                'sahodaya_id'              => $source->sahodaya_id,
                'school_id'                => $schoolId,
                'teacher_id'               => null,
                'subject'                  => $source->subject,
                'class_group'              => $source->class_group,
                'class_category_id'        => $source->class_category_id,
                'master_class_id'          => $source->master_class_id,
                'source_bank_id'           => $source->id,
                'contributed_by_school_id' => $contributedBySchoolId,
                'title'                    => $title,
                'description'              => $source->description,
                'status'                   => 'draft',
                'created_by_user_id'       => $userId,
            ]);

            foreach ($source->questions as $q) {
                $clone = $q->replicate(['bank_id']);
                $clone->bank_id = $copy->id;
                $clone->created_by_user_id = $userId;
                $clone->save();
            }

            $this->audit->log($auditEvent, $auditMessage, $copy, [
                'source_bank_id' => $source->id,
                'copy_bank_id'   => $copy->id,
                'school_id'      => $schoolId ?? $contributedBySchoolId,
                'sahodaya_id'    => $source->sahodaya_id,
            ], category: 'mcq');

            return $copy->fresh();
        });
    }
}
