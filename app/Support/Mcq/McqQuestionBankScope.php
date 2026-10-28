<?php

namespace App\Support\Mcq;

use App\Models\MasterClass;
use App\Models\McqExam;
use App\Models\McqQuestionBank;
use Illuminate\Database\Eloquent\Builder;

/**
 * Match question banks to an exam's class / class-category eligibility.
 * Unscoped banks (no class and no category) are treated as broader and always eligible.
 */
final class McqQuestionBankScope
{
    public static function isUnscoped(McqQuestionBank $bank): bool
    {
        return $bank->master_class_id === null && $bank->class_category_id === null;
    }

    /** Human-readable scope line for bank lists. */
    public static function label(McqQuestionBank $bank): ?string
    {
        $parts = [];
        if ($bank->masterClass?->name) {
            $parts[] = $bank->masterClass->name;
        }
        if ($bank->classCategory?->label) {
            $parts[] = $bank->classCategory->label;
        }
        if ($parts === [] && $bank->class_group) {
            return null; // caller may still show fest class_group_label
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * Whether this bank may be attached to the exam paper given eligibility + sharing rules
     * already applied by the caller (school_id / allow_member_banks).
     */
    public static function matchesExam(McqQuestionBank $bank, McqExam $exam): bool
    {
        if (self::isUnscoped($bank)) {
            return true;
        }

        $config = McqExamEligibilityConfig::normalize($exam->eligibility_config);
        $assignment = $config['assignment_type'] ?? 'all';

        if ($assignment === 'all') {
            return true;
        }

        $examCategoryIds = array_map('intval', $config['class_category_ids'] ?? []);
        $examClassIds = array_map('intval', $config['master_class_ids'] ?? []);

        if ($assignment === 'category') {
            if ($bank->class_category_id !== null && in_array((int) $bank->class_category_id, $examCategoryIds, true)) {
                return true;
            }
            if ($bank->master_class_id !== null && $examCategoryIds !== []) {
                $bankCategoryId = $bank->masterClass?->class_category_id
                    ?? MasterClass::whereKey($bank->master_class_id)->value('class_category_id');

                return $bankCategoryId !== null && in_array((int) $bankCategoryId, $examCategoryIds, true);
            }

            return false;
        }

        // assignment_type === 'class'
        if ($bank->master_class_id !== null && in_array((int) $bank->master_class_id, $examClassIds, true)) {
            return true;
        }

        if ($bank->class_category_id !== null && $examClassIds !== []) {
            $categoryIdsForExamClasses = MasterClass::whereIn('id', $examClassIds)
                ->whereNotNull('class_category_id')
                ->pluck('class_category_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            return in_array((int) $bank->class_category_id, $categoryIdsForExamClasses, true);
        }

        return false;
    }

    /**
     * Restrict a bank query to those attachable for this exam's eligibility.
     * Master class / category rows live on the central connection — resolve IDs first.
     *
     * @param  Builder<\App\Models\McqQuestionBank>  $query
     * @return Builder<\App\Models\McqQuestionBank>
     */
    public static function constrainQueryToExam(Builder $query, McqExam $exam): Builder
    {
        $config = McqExamEligibilityConfig::normalize($exam->eligibility_config);
        $assignment = $config['assignment_type'] ?? 'all';

        if ($assignment === 'all') {
            return $query;
        }

        $examCategoryIds = array_map('intval', $config['class_category_ids'] ?? []);
        $examClassIds = array_map('intval', $config['master_class_ids'] ?? []);

        $matchingClassIds = [];
        $matchingCategoryIds = [];

        if ($assignment === 'category' && $examCategoryIds !== []) {
            $matchingCategoryIds = $examCategoryIds;
            $matchingClassIds = MasterClass::whereIn('class_category_id', $examCategoryIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($assignment === 'class' && $examClassIds !== []) {
            $matchingClassIds = $examClassIds;
            $matchingCategoryIds = MasterClass::whereIn('id', $examClassIds)
                ->whereNotNull('class_category_id')
                ->pluck('class_category_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return $query->where(function (Builder $outer) use ($matchingClassIds, $matchingCategoryIds) {
            $outer->where(function (Builder $u) {
                $u->whereNull('master_class_id')->whereNull('class_category_id');
            });
            if ($matchingCategoryIds !== []) {
                $outer->orWhereIn('class_category_id', $matchingCategoryIds);
            }
            if ($matchingClassIds !== []) {
                $outer->orWhereIn('master_class_id', $matchingClassIds);
            }
        });
    }

    /** @return array{class_category_ids: list<int>, master_class_ids: list<int>, assignment_type: string} */
    public static function examFilterMeta(McqExam $exam): array
    {
        $config = McqExamEligibilityConfig::normalize($exam->eligibility_config);

        return [
            'assignment_type'    => $config['assignment_type'] ?? 'all',
            'class_category_ids' => array_map('intval', $config['class_category_ids'] ?? []),
            'master_class_ids'   => array_map('intval', $config['master_class_ids'] ?? []),
        ];
    }
}
