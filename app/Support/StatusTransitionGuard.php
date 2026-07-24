<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class StatusTransitionGuard
{
    /** Legal status transitions for Fest Events */
    public const FEST_EVENT_TRANSITIONS = [
        'draft'             => ['published', 'cancelled'],
        'published'         => ['registration_open', 'draft', 'cancelled'],
        'registration_open' => ['ongoing', 'published', 'cancelled'],
        'ongoing'           => ['completed', 'cancelled'],
        'completed'         => [], // Completed events cannot be transitioned back
        'cancelled'         => ['draft'], // Admin re-opening
    ];

    /** Legal status transitions for MCQ Exams */
    public const MCQ_EXAM_TRANSITIONS = [
        'draft'     => ['published', 'cancelled'],
        'published' => ['ongoing', 'draft', 'cancelled'],
        'ongoing'   => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => ['draft'],
    ];

    /** Legal status transitions for Training Programs */
    public const TRAINING_PROGRAM_TRANSITIONS = [
        'draft'             => ['published', 'cancelled'],
        'published'         => ['registration_open', 'draft', 'cancelled'],
        'registration_open' => ['ongoing', 'published', 'cancelled'],
        'ongoing'           => ['completed', 'cancelled'],
        'completed'         => [],
        'cancelled'         => ['draft'],
    ];

    /**
     * Asserts that a model transition from its current status to $toStatus is allowed.
     *
     * @param  array<string, list<string>>|list<string>  $allowedRules  Either a transition matrix or allowed 'from' statuses
     */
    public static function assert(
        Model $model,
        string $toStatus,
        array $allowedRules,
        string $statusColumn = 'status',
        ?string $errorMessage = null,
    ): void {
        $current = (string) ($model->{$statusColumn} ?? '');
        if ($current === $toStatus) {
            return;
        }

        $isAllowed = false;

        // Matrix mode (currentStatus => [allowedNextStatuses])
        if (array_is_list($allowedRules) === false && isset($allowedRules[$current])) {
            $isAllowed = in_array($toStatus, $allowedRules[$current], true);
        } elseif (array_is_list($allowedRules)) {
            // Explicit allowed-from list (e.g. allowedFrom: ['draft', 'published'])
            $isAllowed = in_array($current, $allowedRules, true);
        }

        if (! $isAllowed) {
            $msg = $errorMessage ?? "Cannot transition {$model->getTable()} from '{$current}' to '{$toStatus}'.";
            throw ValidationException::withMessages([
                $statusColumn => $msg,
            ]);
        }
    }
}
