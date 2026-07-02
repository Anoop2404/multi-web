<?php

namespace App\Services\School;

use App\Models\FestEvent;
use App\Models\McqExam;
use App\Models\SchoolUserEventScope;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\SchoolFestProgram;
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

        if ($scopeType === 'program' || $scope->event_id === null) {
            return true;
        }

        if ($eventId === null) {
            return true;
        }

        if ($scopeTypeHint && $scopeType !== $scopeTypeHint) {
            return false;
        }

        return (int) $scope->event_id === (int) $eventId;
    }

    /** @return list<array{slug: string, label: string}> */
    private function programCatalog(): array
    {
        return [
            ['slug' => 'kalotsav', 'label' => 'Kalotsav'],
            ['slug' => 'sports-meet', 'label' => 'Sports Meet'],
            ['slug' => 'kids-fest', 'label' => 'Kids Fest'],
            ['slug' => 'teacher-fest', 'label' => 'Teacher Fest'],
            ['slug' => 'mcq', 'label' => 'MCQ Exams'],
            ['slug' => 'training', 'label' => 'Training Programs'],
        ];
    }
}
