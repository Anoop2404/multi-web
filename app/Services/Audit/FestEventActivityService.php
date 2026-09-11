<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Services\Events\PublicFestScoreboardService;
use Illuminate\Support\Collection;

class FestEventActivityService
{
    /** @return Collection<int, array<string, mixed>> */
    public function forPage(FestEvent $event, string $page, int $limit = 20): Collection
    {
        return $this->query($event, $page, $limit);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function forEvent(FestEvent $event, int $limit = 20, ?string $page = null, ?int $itemId = null, ?string $search = null): Collection
    {
        return $this->query($event, $page, $limit, $itemId, $search);
    }

    /** @return list<array<string, mixed>> */
    public function forProgram(string $tenantId, string $program, int $limit = 20): array
    {
        return AuditLog::query()
            ->with('user:id,name,email')
            ->where('properties->tenant_id', $tenantId)
            ->where('properties->program', $program)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id'          => $log->id,
                'action'      => $log->action,
                'description' => $log->description,
                'page'        => $log->properties['page'] ?? null,
                'item_id'     => $log->properties['item_id'] ?? null,
                'item_title'  => $log->properties['item_title'] ?? null,
                'chest_no'    => $log->properties['chest_no'] ?? null,
                'participant' => $log->properties['participant'] ?? null,
                'school'      => $log->properties['school'] ?? null,
                'ip_address'  => $log->ip_address,
                'user'        => $log->user?->only('id', 'name', 'email'),
                'created_at'  => $log->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function forCatalog(string $tenantId, string $page, int $limit = 20): array
    {
        return AuditLog::query()
            ->with('user:id,name,email')
            ->where('properties->tenant_id', $tenantId)
            ->where('properties->page', $page)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id'          => $log->id,
                'action'      => $log->action,
                'description' => $log->description,
                'ip_address'  => $log->ip_address,
                'user'        => $log->user?->only('id', 'name', 'email'),
                'created_at'  => $log->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function query(FestEvent $event, ?string $page, int $limit, ?int $itemId = null, ?string $search = null): Collection
    {
        $morph = (new FestEvent)->getMorphClass();
        $eventId = (string) $event->id;
        // A hub event's own activity log needs to include what happened under its region
        // children too — registrations, marks, attendance, and chest-number actions are all
        // logged against the CHILD region's event id (that's the event the action actually
        // ran against), never the hub's. Every other hub-aware admin page (chest numbers,
        // marks, reports) already scopes this way; this query previously matched only the
        // exact hub id, so a hub's Activity Log page could never show any region activity —
        // "no logged actions" even when the region logs plainly existed.
        $reportableEventIds = $event->reportableEventIds();

        $searchParticipantIds = [];
        if ($search !== null && $search !== '') {
            $term = '%'.strtolower(trim($search)).'%';
            $searchParticipantIds = FestParticipant::whereHas('registration', fn ($q) => $q->whereIn('event_id', $event->reportableEventIds()))
                ->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(CAST(chest_no AS TEXT)) LIKE ?', [$term])
                      ->orWhereHas('group', fn ($g) => $g->whereRaw('LOWER(CAST(chest_no AS TEXT)) LIKE ?', [$term])->orWhereRaw('LOWER(team_name) LIKE ?', [$term]))
                      ->orWhereHas('student', fn ($s) => $s->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(CAST(reg_no AS TEXT)) LIKE ?', [$term]))
                      ->orWhereHas('teacher', fn ($t) => $t->whereRaw('LOWER(name) LIKE ?', [$term]));
                })
                ->pluck('id')
                ->all();
        }

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->where(function ($q) use ($morph, $eventId, $reportableEventIds) {
                $q->where(function ($q2) use ($morph, $eventId) {
                    $q2->where('subject_type', $morph)->where('subject_id', $eventId);
                })->orWhereIn('properties->event_id', $reportableEventIds);
            })
            ->when($page !== null && $page !== '', fn ($q) => $q->where('properties->page', $page))
            ->when($itemId !== null, function ($q) use ($itemId) {
                $q->where(function ($q2) use ($itemId) {
                    $q2->where('properties->item_id', $itemId)
                       ->orWhere('properties->item_id', (string) $itemId);
                });
            })
            ->when($search !== null && $search !== '', function ($q) use ($search, $searchParticipantIds) {
                $term = '%'.strtolower($search).'%';
                $q->where(function ($q2) use ($term, $searchParticipantIds) {
                    $q2->whereRaw('LOWER(description) LIKE ?', [$term])
                       ->orWhereHas('user', fn ($u) => $u->whereRaw('LOWER(name) LIKE ?', [$term]))
                       ->orWhereRaw('LOWER(CAST(properties AS TEXT)) LIKE ?', [$term])
                       ->orWhereRaw('LOWER(COALESCE(ip_address, \'\')) LIKE ?', [$term]);

                    if (! empty($searchParticipantIds)) {
                        foreach ($searchParticipantIds as $pid) {
                            $q2->orWhere('properties->participant_id', $pid)
                               ->orWhere('properties->participant_id', (string) $pid)
                               ->orWhere('description', 'LIKE', "%participant #{$pid}%");
                        }
                    }
                });
            })
            ->latest()
            ->limit($limit)
            ->get();

        // Batch resolve missing participant details from description regex or properties
        $missingParticipantIds = [];
        $itemIds = [];

        foreach ($logs as $log) {
            $props = $log->properties ?? [];
            if (! empty($props['item_id'])) {
                $itemIds[] = (int) $props['item_id'];
            }
            $pid = $props['participant_id'] ?? null;
            if (! $pid && preg_match('/participant\s+#(\d+)/i', $log->description, $matches)) {
                $pid = (int) $matches[1];
            }
            if ($pid) {
                $missingParticipantIds[] = (int) $pid;
            }
        }

        $participantsMap = collect();
        $marksMap = collect();
        if (! empty($missingParticipantIds)) {
            $participantsMap = FestParticipant::whereIn('id', array_unique($missingParticipantIds))
                ->with(['student', 'teacher', 'group', 'registration.school', 'registration.item'])
                ->get()
                ->keyBy('id');

            $marksMap = \App\Models\FestMark::whereIn('participant_id', array_unique($missingParticipantIds))
                ->get()
                ->keyBy(fn ($m) => "{$m->item_id}-{$m->participant_id}");
        }

        $itemsMap = collect();
        if (! empty($itemIds)) {
            $itemsMap = FestEventItem::whereIn('id', array_unique($itemIds))
                ->get()
                ->keyBy('id');
        }

        // Registration-level actions (approve/reject/cancel/submit) log the registration
        // itself as the audit subject — subject_id is always the registration id, even for
        // entries written before item_title/participant/school were captured into
        // properties (see PlatformAuditLogger::registrationContext()). Falling back to the
        // registration's CURRENT item/participants/school here means old entries display
        // this too, not just ones logged after that enrichment shipped — no backfill
        // migration needed, just a live lookup by the id the log already carried.
        $registrationMorph = (new FestRegistration)->getMorphClass();
        $missingRegistrationIds = $logs
            ->filter(function (AuditLog $log) use ($registrationMorph) {
                $props = $log->properties ?? [];

                return $log->subject_type === $registrationMorph
                    && $log->subject_id !== null
                    && (empty($props['item_title']) || empty($props['participant']) || empty($props['school']));
            })
            ->pluck('subject_id')
            ->filter()
            ->unique()
            ->all();

        $registrationsMap = collect();
        if (! empty($missingRegistrationIds)) {
            $registrationsMap = FestRegistration::whereIn('id', $missingRegistrationIds)
                ->with(['item', 'school', 'participants.student', 'participants.teacher', 'participants.group'])
                ->get()
                ->keyBy('id');
        }

        $scoreboards = app(PublicFestScoreboardService::class);
        $gradePointService = app(\App\Services\Events\FestGradePointService::class);
        $itemResultsService = app(\App\Services\Events\FestItemResultsService::class);

        $mapped = $logs->map(function (AuditLog $log) use ($participantsMap, $marksMap, $itemsMap, $registrationsMap, $registrationMorph, $event, $scoreboards, $gradePointService, $itemResultsService) {
            $props = $log->properties ?? [];

            if ($log->subject_type === $registrationMorph && $log->subject_id !== null) {
                $registration = $registrationsMap->get((int) $log->subject_id);
                if ($registration) {
                    if (empty($props['item_title'])) {
                        $props['item_title'] = $registration->item?->title;
                    }
                    if (empty($props['item_id'])) {
                        $props['item_id'] = $registration->item_id;
                    }
                    if (empty($props['school'])) {
                        $props['school'] = $registration->school?->name;
                    }
                    if (empty($props['participant'])) {
                        $names = $registration->participants
                            ->map(fn ($p) => $p->student?->name ?? $p->teacher?->name ?? $p->group?->team_name)
                            ->filter()
                            ->unique()
                            ->implode(', ');
                        if ($names !== '') {
                            $props['participant'] = $names;
                        }
                    }
                }
            }
            $pid = $props['participant_id'] ?? null;
            if (! $pid && preg_match('/participant\s+#(\d+)/i', $log->description, $matches)) {
                $pid = (int) $matches[1];
            }

            $participant = $pid ? $participantsMap->get((int) $pid) : null;
            $itemId = $props['item_id'] ?? $participant?->registration?->item_id;
            $markRecord = ($itemId && $pid) ? $marksMap->get("{$itemId}-{$pid}") : ($pid ? $marksMap->filter(fn ($m) => $m->participant_id == $pid)->first() : null);

            if ($markRecord) {
                if (! isset($props['score']) && $markRecord->score !== null) {
                    $props['score'] = (float) $markRecord->score;
                }
                if (! isset($props['grade']) && $markRecord->grade !== null) {
                    $props['grade'] = $markRecord->grade;
                }
                if (! isset($props['position']) && $markRecord->position !== null) {
                    $props['position'] = (int) $markRecord->position;
                }
                if (! isset($props['measurement_value']) && $markRecord->measurement_value !== null) {
                    $props['measurement_value'] = $markRecord->measurement_value;
                    $props['measurement_unit'] = $markRecord->measurement_unit;
                }
                if (! isset($props['judge_scores']) && ! empty($markRecord->ref_data_json['judge_scores'])) {
                    $props['judge_scores'] = $markRecord->ref_data_json['judge_scores'];
                }
            }

            // Derive Grade from score if grade is still missing
            $score = isset($props['score']) ? (float) $props['score'] : null;
            if (empty($props['grade']) && $score !== null) {
                $derivedGrade = $gradePointService->resolveGradeFromScore($event, $itemId ? (int) $itemId : null, $score);
                if ($derivedGrade) {
                    $props['grade'] = $derivedGrade;
                }
            }

            // Derive Position / Rank if position is still missing
            if (empty($props['position']) && $itemId && $pid) {
                $itemResultRows = $itemResultsService->resultRowsForItem($event, (int) $itemId);
                foreach ($itemResultRows as $row) {
                    if (($row['participant_id'] ?? null) == $pid && ! empty($row['position'])) {
                        $props['position'] = (int) $row['position'];
                        break;
                    }
                }
            }
            $personName = $props['participant'] ?? $participant?->student?->name ?? $participant?->teacher?->name ?? $participant?->group?->name;
            $chestNo = $props['chest_no'] ?? $participant?->group?->chest_no ?? $participant?->chest_no;
            $schoolName = $props['school'] ?? $participant?->registration?->school?->name;
            $regNo = $participant?->student?->reg_no ?? $participant?->teacher?->reg_no;

            $itemId = $props['item_id'] ?? $participant?->registration?->item_id;
            $itemModel = $itemId ? $itemsMap->get((int) $itemId) : null;
            $itemTitle = $props['item_title'] ?? $itemModel?->title ?? $participant?->registration?->item?->title;
            $categoryKey = $itemModel?->class_group ?? $itemModel?->age_group;
            $categoryLabel = $categoryKey ? $scoreboards->categoryLabel($event, $categoryKey) : null;

            $description = $log->description;
            if (str_starts_with($description, 'Mark saved for participant #') && $participant) {
                $chestLabel = $chestNo ? "Chest #{$chestNo}" : "Participant #{$pid}";
                $itemLabel = $itemTitle ? " in {$itemTitle}" : '';
                $schoolLabel = $schoolName ? " ({$schoolName})" : '';

                $details = [];
                if (! empty($props['position'])) {
                    $details[] = "Rank #{$props['position']}";
                }
                if (isset($props['score']) && $props['score'] !== null && $props['score'] !== '') {
                    $details[] = "Score: {$props['score']}";
                }
                if (! empty($props['grade'])) {
                    $details[] = "Grade: {$props['grade']}";
                }
                $detailStr = $details !== [] ? ' [' . implode(', ', $details) . ']' : '';

                $description = "Mark saved for {$chestLabel} - {$personName}{$schoolLabel}{$itemLabel}{$detailStr}";
            }

            return [
                'id'            => $log->id,
                'action'        => $log->action,
                'description'   => $description,
                'page'          => $props['page'] ?? null,
                'item_id'       => $itemId,
                'item_title'    => $itemTitle,
                'item_code'     => $itemModel?->item_code,
                'item_category' => $categoryLabel,
                'chest_no'      => $chestNo,
                'participant'   => $personName,
                'school'        => $schoolName,
                'reg_no'        => $regNo,
                'reason'        => $props['reason'] ?? null,
                'ip_address'    => $log->ip_address,
                'user_agent'    => $props['user_agent'] ?? null,
                'actor_type'    => $props['actor_type'] ?? null,
                'user'          => $log->user?->only('id', 'name', 'email'),
                'properties'    => $props,
                'created_at'    => $log->created_at?->toIso8601String(),
            ];
        });

        if ($search !== null && $search !== '') {
            $rawTerms = array_filter(explode(' ', strtolower(trim($search))));
            $mapped = $mapped->filter(function ($row) use ($rawTerms) {
                $haystack = strtolower(implode(' ', array_filter([
                    $row['description'] ?? '',
                    $row['chest_no'] ?? '',
                    isset($row['chest_no']) ? "chest #{$row['chest_no']}" : '',
                    $row['participant'] ?? '',
                    $row['school'] ?? '',
                    $row['reg_no'] ?? '',
                    $row['item_title'] ?? '',
                    $row['item_category'] ?? '',
                    $row['item_code'] ?? '',
                    $row['user']['name'] ?? '',
                    $row['ip_address'] ?? '',
                    $row['reason'] ?? '',
                    $row['actor_type'] ?? '',
                ])));

                foreach ($rawTerms as $term) {
                    if (! str_contains($haystack, $term)) {
                        return false;
                    }
                }

                return true;
            })->values();
        }

        return $mapped;
    }
}
