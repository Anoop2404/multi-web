<?php

namespace App\Http\Controllers\ExamEngine\Support;

/**
 * Who is looking at a live monitor and what they may do there (plan §5.4):
 * the Sahodaya exam team gets every action, school coordinators get first-line support
 * actions for their own school only, exam-day staff get a read-only view.
 */
final class LiveMonitorScope
{
    /**
     * @param  string  $via  sahodaya | exam_portal | school — recorded on every audit row
     * @param  string  $actor  "user:{id} ({role})", sent to the engine with every action
     * @param  list<string>  $attemptActions  per-candidate actions allowed (LiveMonitor::ATTEMPT_ACTIONS)
     * @param  bool  $runActions  broadcast, pacing and close
     * @param  string|null  $schoolId  restricts every read and action to one school
     * @param  int|null  $extendCap  total minutes this scope may add per candidate; null = no cap
     */
    public function __construct(
        public readonly string $via,
        public readonly string $actor,
        public readonly array $attemptActions,
        public readonly bool $runActions,
        public readonly ?string $schoolId = null,
        public readonly ?int $extendCap = null,
    ) {}

    public function can(string $action): bool
    {
        return in_array($action, ['broadcast', 'pace', 'close', 'extend_all', 'hold'], true)
            ? $this->runActions
            : in_array($action, $this->attemptActions, true);
    }

    public function canAct(): bool
    {
        return $this->runActions || $this->attemptActions !== [];
    }

    public function isSchool(): bool
    {
        return $this->schoolId !== null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'via'             => $this->via,
            'attempt_actions' => array_values($this->attemptActions),
            'run_actions'     => $this->runActions,
            'can_act'         => $this->canAct(),
            'extend_cap'      => $this->extendCap,
            'school_only'     => $this->isSchool(),
        ];
    }
}
