<?php

namespace App\Services\School;

use App\Models\FestEvent;
use App\Models\McqExam;
use App\Models\SchoolUserEventScope;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\ProgramRouteMap;
use App\Support\SchoolFestProgram;
use App\Support\TenantUserCatalog;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SchoolUserScopeService
{
    /** @return array{programs: list<array>, fest_events: list<array>, mcq_exams: list<array>, training_programs: list<array>} */
    public function scopeOptionsForSchool(string $schoolId, ?string $sahodayaId): array
    {
        if (! $sahodayaId) {
            return [
                'programs'          => $this->programCatalog(),
                'fest_events'       => [],
                'mcq_exams'         => [],
                'training_programs' => [],
            ];
        }

        $festEvents = FestEvent::where('tenant_id', $sahodayaId)
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'event_type', 'status', 'event_start'])
            ->map(fn (FestEvent $e) => [
                'id'           => $e->id,
                'title'        => $e->title,
                'program_slug' => SchoolFestProgram::slugForEventType($e->event_type),
                'status'       => $e->status,
                'event_start'  => $e->event_start?->format('Y-m-d'),
            ])
            ->values()
            ->all();

        $mcqExams = McqExam::where('tenant_id', $sahodayaId)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->orderByDesc('scheduled_at')
            ->get(['id', 'title', 'status', 'scheduled_at', 'exam_level'])
            ->map(fn (McqExam $e) => [
                'id'         => $e->id,
                'title'      => $e->title,
                'status'     => $e->status,
                'exam_level' => $e->exam_level ?? 1,
                'scheduled_at' => $e->scheduled_at?->format('Y-m-d'),
            ])
            ->values()
            ->all();

        $trainingPrograms = TrainingProgram::where('tenant_id', $sahodayaId)
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->orderByDesc('registration_open')
            ->get(['id', 'title', 'status', 'registration_open'])
            ->map(fn (TrainingProgram $p) => [
                'id'                => $p->id,
                'title'             => $p->title,
                'status'            => $p->status,
                'registration_open' => $p->registration_open?->format('Y-m-d'),
            ])
            ->values()
            ->all();

        return [
            'programs'          => $this->programCatalog(),
            'fest_events'       => $festEvents,
            'mcq_exams'         => $mcqExams,
            'training_programs' => $trainingPrograms,
        ];
    }

    /** @return list<array{program_slug: string, scope_type: string, event_id: ?int, label: string}> */
    public function scopesForUser(int $userId, string $schoolId): array
    {
        return SchoolUserEventScope::where('user_id', $userId)
            ->where('school_id', $schoolId)
            ->get()
            ->map(fn (SchoolUserEventScope $s) => [
                'program_slug' => $s->program_slug,
                'scope_type'   => $s->scope_type ?? 'program',
                'event_id'     => $s->event_id,
            ])
            ->values()
            ->all();
    }

    /** @param  list<array{program_slug?: string, scope_type?: string, event_id?: int|null}>  $scopes */
    public function sync(User $user, string $schoolId, array $scopes, ?int $createdByUserId = null): void
    {
        if (! $user->hasRole('school_event_coordinator')) {
            SchoolUserEventScope::where('user_id', $user->id)->where('school_id', $schoolId)->delete();

            return;
        }

        $normalized = [];
        foreach ($scopes as $scope) {
            $programSlug = $scope['program_slug'] ?? null;
            $scopeType = $scope['scope_type'] ?? 'program';
            $eventId = $scope['event_id'] ?? null;

            if (! $programSlug) {
                continue;
            }

            if ($scopeType === 'program') {
                $eventId = null;
            } elseif ($eventId === null) {
                throw ValidationException::withMessages([
                    'event_scopes' => 'Select a specific event for each coordinator assignment.',
                ]);
            }

            $key = "{$programSlug}:{$scopeType}:{$eventId}";
            $normalized[$key] = [
                'program_slug' => $programSlug,
                'scope_type'   => $scopeType,
                'event_id'     => $eventId,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'event_scopes' => 'Assign at least one program or event for event coordinators.',
            ]);
        }

        SchoolUserEventScope::where('user_id', $user->id)->where('school_id', $schoolId)->delete();

        foreach ($normalized as $scope) {
            SchoolUserEventScope::create([
                'school_id'    => $schoolId,
                'user_id'      => $user->id,
                'program_slug' => $scope['program_slug'],
                'scope_type'   => $scope['scope_type'],
                'event_id'     => $scope['event_id'],
                'created_by'   => $createdByUserId,
            ]);
        }
    }

    public function scopeMatchesRoute(SchoolUserEventScope $scope, ?string $programSlug, ?int $eventId, ?string $scopeTypeHint): bool
    {
        if ($programSlug && $scope->program_slug !== 'all' && $scope->program_slug !== $programSlug) {
            return false;
        }

        $scopeType = $scope->scope_type ?? 'program';

        if ($scopeType === 'program') {
            return true;
        }

        if ($scopeTypeHint && $scopeType !== $scopeTypeHint) {
            return false;
        }

        if ($eventId !== null) {
            return (int) $scope->event_id === (int) $eventId;
        }

        return $programSlug !== null;
    }

    public function homeUrlFor(User $user, string $schoolId): string
    {
        $scopes = $this->scopesForUser($user->id, $schoolId);
        if ($scopes === []) {
            return "/school-admin/{$schoolId}";
        }

        $first = $scopes[0];
        $slug = $first['program_slug'];

        if ($slug === 'mcq') {
            if (($first['scope_type'] ?? '') === 'mcq_exam' && ! empty($first['event_id'])) {
                return "/school-admin/{$schoolId}/mcq/{$first['event_id']}/register";
            }

            return "/school-admin/{$schoolId}/mcq";
        }

        if ($slug === 'training') {
            return "/school-admin/{$schoolId}/training";
        }

        $prefix = ProgramRouteMap::prefixFromSlug($slug) ?? $slug;

        if (($first['scope_type'] ?? '') === 'fest_event' && ! empty($first['event_id'])) {
            return "/school-admin/{$schoolId}/{$prefix}/events/{$first['event_id']}/overview";
        }

        return "/school-admin/{$schoolId}/{$prefix}";
    }

    /** @return list<array{program_slug: string, scope_type: string, event_id: ?int, label: string}> */
    public function scopesWithLabels(int $userId, string $schoolId, ?string $sahodayaId = null): array
    {
        $options = $sahodayaId ? $this->scopeOptionsForSchool($schoolId, $sahodayaId) : [];
        $labels = collect($options['fest_events'] ?? [])
            ->keyBy('id')
            ->map(fn ($e) => $e['title']);

        foreach ($options['mcq_exams'] ?? [] as $exam) {
            $labels[(int) $exam['id']] = $exam['title'];
        }
        foreach ($options['training_programs'] ?? [] as $program) {
            $labels[(int) $program['id']] = $program['title'];
        }

        $programLabels = collect($this->programCatalog())->pluck('label', 'slug');

        return collect($this->scopesForUser($userId, $schoolId))
            ->map(function (array $scope) use ($labels, $programLabels) {
                $slug = $scope['program_slug'];
                $type = $scope['scope_type'] ?? 'program';
                $eventId = $scope['event_id'] ?? null;

                if ($type === 'program') {
                    $label = $programLabels[$slug] ?? $slug;
                    $label = "All {$label} events";
                } elseif ($eventId && $labels->has($eventId)) {
                    $label = $labels[$eventId];
                } else {
                    $label = ($programLabels[$slug] ?? $slug).' #'.($eventId ?? '?');
                }

                return array_merge($scope, ['label' => $label]);
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, FestEvent>  $events
     * @return Collection<int, FestEvent>
     */
    public function filterFestEventsForUser(?User $user, string $schoolId, string $programSlug, Collection $events): Collection
    {
        if (! $user || ! $user->hasRole('school_event_coordinator') || $user->hasAnyRole(TenantUserCatalog::schoolManagementRoles())) {
            return $events;
        }

        $scopes = SchoolUserEventScope::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->where('program_slug', $programSlug)
            ->get();

        if ($scopes->contains(fn (SchoolUserEventScope $s) => ($s->scope_type ?? 'program') === 'program')) {
            return $events;
        }

        $eventIds = $scopes
            ->filter(fn (SchoolUserEventScope $s) => ($s->scope_type ?? '') === 'fest_event' && $s->event_id)
            ->pluck('event_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($eventIds === []) {
            return collect();
        }

        return $events->whereIn('id', $eventIds)->values();
    }

    /** @return list<string> */
    public function assignedProgramSlugs(int $userId, string $schoolId): array
    {
        return SchoolUserEventScope::where('user_id', $userId)
            ->where('school_id', $schoolId)
            ->pluck('program_slug')
            ->unique()
            ->values()
            ->all();
    }

    public function isCoordinatorOnly(User $user): bool
    {
        return $user->hasRole('school_event_coordinator')
            && ! $user->hasAnyRole(TenantUserCatalog::schoolManagementRoles())
            && ! $user->isSuperAdmin();
    }

    /** @return list<array{slug: string, label: string}> */
    private function programCatalog(): array
    {
        return [
            ['slug' => 'kalotsav', 'label' => 'Kalotsav'],
            ['slug' => 'sports-meet', 'label' => 'Sports Meet'],
            ['slug' => 'kids-fest', 'label' => 'Kids Fest'],
            ['slug' => 'teacher-fest', 'label' => 'Teacher Fest'],
            ['slug' => 'english-fest', 'label' => 'English Fest'],
            ['slug' => 'science-fest', 'label' => 'Science Fest'],
            ['slug' => 'mcq', 'label' => 'Talent Search Exams'],
            ['slug' => 'training', 'label' => 'Training Programs'],
        ];
    }
}
