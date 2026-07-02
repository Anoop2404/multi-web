<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramPropagation;
use App\Models\Tenant;
use App\Support\AcademicYear;
use App\Support\FestConductLevels;
use App\Support\TenancyDatabase;

class FestStateProgramService
{
    /**
     * Publish a state program to all active Sahodaya clusters.
     *
     * @return array{propagated: int, skipped: int, errors: list<string>}
     */
    public function publish(FestStateProgram $program): array
    {
        $levels = FestConductLevels::normalize($program->conduct_levels ?? [], $program->event_type);
        if ($levels === []) {
            $levels = FestConductLevels::defaultsFor($program->event_type);
        }
        if ($levels === []) {
            throw new \InvalidArgumentException('Select at least one conduct level before publishing.');
        }

        $propagated = 0;
        $skipped = 0;
        $errors = [];

        $sahodayas = Tenant::query()
            ->sahodayas()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        foreach ($sahodayas as $sahodaya) {
            foreach ($levels as $level) {
                if (! FestConductLevels::isAllowed($level, $program->event_type)) {
                    continue;
                }

                $existing = FestStateProgramPropagation::query()
                    ->where('state_program_id', $program->id)
                    ->where('sahodaya_id', $sahodaya->id)
                    ->where('level_round', $level)
                    ->first();

                if ($existing?->tenant_event_id) {
                    $skipped++;

                    continue;
                }

                try {
                    TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($program, $sahodaya, $level, &$propagated) {
                        $event = $this->createTenantEvent($program, $sahodaya, $level);

                        app(FestItemSyncService::class)->syncProgramToEvent($program, $event);

                        FestStateProgramPropagation::updateOrCreate(
                            [
                                'state_program_id' => $program->id,
                                'sahodaya_id'      => $sahodaya->id,
                                'level_round'      => $level,
                            ],
                            ['tenant_event_id' => $event->id]
                        );

                        $propagated++;
                    });
                } catch (\Throwable $e) {
                    $errors[] = "{$sahodaya->name} ({$level}): {$e->getMessage()}";
                }
            }
        }

        if ($program->status !== 'published') {
            $program->update(['status' => 'published']);
        }

        return compact('propagated', 'skipped', 'errors');
    }

    public function createTenantEvent(FestStateProgram $program, Tenant $sahodaya, string $levelRound): FestEvent
    {
        $academicYearId = AcademicYear::activeId();

        $fee = app(FestEventFeeResolver::class)->resolveForProgram($program, $levelRound);
        $feeModel = $fee['fee_model'] ?? 'none';

        $event = FestEvent::create([
            'tenant_id'          => $sahodaya->id,
            'academic_year_id'   => $academicYearId,
            'title'              => $program->title,
            'event_type'         => $program->event_type,
            'conductor_level'    => 'state',
            'conduct_levels'     => FestConductLevels::normalize(
                $program->conduct_levels ?? [],
                $program->event_type
            ) ?: FestConductLevels::defaultsFor($program->event_type),
            'level_round'        => $levelRound,
            'state_program_id'   => $program->id,
            'registration_open'  => $program->registration_open,
            'registration_close' => $program->registration_close,
            'event_start'        => $program->event_start,
            'event_end'          => $program->event_end,
            'venue'              => $program->venue,
            'fee_type'           => $feeModel === 'none' ? 'none' : 'per_item',
            'fee_amount'         => null,
            'status'             => 'draft',
            'description'        => $program->description,
        ]);

        app(FestParticipationPolicyService::class)->copyFromStateProgram($event, $program);

        return $event;
    }

    /**
     * Spawn school-round child events for each member school.
     *
     * @return list<FestEvent>
     */
    public function spawnSchoolRounds(FestEvent $parent, ?array $schoolIds = null): array
    {
        abort_if($parent->tenant_id === null, 422);
        abort_unless($parent->conductsAt('school'), 422, 'This event does not include school-level rounds.');

        $schoolIds ??= TenancyDatabase::schoolIdsFor($parent->tenant_id);
        $created = [];

        foreach ($schoolIds as $schoolId) {
            $school = Tenant::query()->find($schoolId);
            if (! $school || $school->type !== 'school') {
                continue;
            }

            $exists = FestEvent::query()
                ->where('parent_event_id', $parent->id)
                ->where('conducting_school_id', $schoolId)
                ->exists();

            if ($exists) {
                continue;
            }

            $schoolFee = app(FestEventFeeResolver::class)->resolveSchoolRoundFromParent($parent);
            $schoolFeeModel = $schoolFee['fee_model'] ?? 'none';

            $child = app(FestCascadeService::class)->spawnChildEvent(
                $parent,
                "{$parent->title} — {$school->name}",
                [
                    'level_round'           => 'school',
                    'conducting_school_id'  => $schoolId,
                    'conduct_levels'        => ['school'],
                    'conductor_level'       => $parent->conductor_level,
                    'fee_type'              => $schoolFeeModel === 'none' ? 'none' : 'per_item',
                    'fee_amount'            => null,
                ]
            );

            $created[] = $child;

            if ($parent->state_program_id) {
                $program = FestStateProgram::find($parent->state_program_id);
                if ($program) {
                    app(FestParticipationPolicyService::class)->copyFromStateProgram($child, $program);
                }
            } else {
                app(FestParticipationPolicyService::class)->applyPresetToEvent($child, 'cksc_school_kalakriti');
            }
        }

        return $created;
    }
}
