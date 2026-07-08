<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestRegistration;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

class FestBulkRegistrationService
{
    public function __construct(
        private FestRegistrationCreateService $createService,
        private FestEventRegistrationService $eventRegistrationService,
    ) {}

    /**
     * Register multiple students for multiple items in one action.
     *
     * @param  list<int>  $studentIds
     * @param  list<int>  $itemIds
     * @param  array<int, list<int>>  $standbysByItem  item_id => standby student ids
     * @return array{created: int, errors: list<string>}
     */
    public function assignStudentsToItems(
        FestEvent $event,
        Tenant $school,
        array $studentIds,
        array $itemIds,
        array $standbysByItem = [],
        bool $registerForEventFirst = true,
    ): array {
        $studentIds = array_values(array_unique(array_filter($studentIds)));
        $itemIds = array_values(array_unique(array_filter($itemIds)));
        $created = 0;
        $errors = [];

        if ($registerForEventFirst && $this->eventRegistrationService->requireEventRegistration($event)) {
            try {
                $this->eventRegistrationService->registerStudents($event, $school, $studentIds);
            } catch (\Throwable $e) {
                $errors[] = $this->failureMessage($e);
            }
        }

        foreach ($itemIds as $itemId) {
            $item = FestEventItem::where('event_id', $event->id)->find($itemId);
            if (! $item) {
                $errors[] = "Item #{$itemId} not found.";

                continue;
            }

            try {
                app(FestItemRegistrationGate::class)->assertOpen($item);
            } catch (\Throwable $e) {
                $errors[] = "{$item->title}: {$this->failureMessage($e)}";

                continue;
            }

            $standbys = array_values(array_unique($standbysByItem[$itemId] ?? []));

            if (in_array($item->participant_type, ['group', 'team'], true)) {
                try {
                    $this->createService->createForSchool(
                        $event,
                        $item,
                        $school,
                        $studentIds,
                        $standbys,
                        teamName: 'Team '.substr(md5(implode('-', $studentIds)), 0, 6),
                    );
                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = "{$item->title}: {$this->failureMessage($e)}";
                }

                continue;
            }

            foreach ($studentIds as $studentId) {
                if (in_array($studentId, $standbys, true)) {
                    continue;
                }

                if ($this->duplicateExists($event, $item, $school->id, $studentId)) {
                    continue;
                }

                try {
                    $this->createService->createForSchool(
                        $event,
                        $item,
                        $school,
                        [$studentId],
                        array_values(array_intersect($standbys, [$studentId])),
                    );
                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = "{$item->title} / student #{$studentId}: {$this->failureMessage($e)}";
                }
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    private function duplicateExists(FestEvent $event, FestEventItem $item, string $schoolId, int $studentId): bool
    {
        return FestRegistration::where('event_id', $event->id)
            ->where('item_id', $item->id)
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved'])
            ->whereHas('participants', fn ($q) => $q
                ->where('student_id', $studentId)
                ->where('participant_role', 'performer'))
            ->exists();
    }

    private function failureMessage(\Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            $messages = collect($e->errors())->flatten()->filter()->values();
            if ($messages->isNotEmpty()) {
                return $messages->implode(' ');
            }
        }

        return $e->getMessage();
    }
}
