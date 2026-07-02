<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestParticipationPolicy;
use App\Models\FestStateProgram;

class FestParticipationPolicyService
{
    public function preset(string $key): array
    {
        return config("fest_participation_presets.{$key}", []);
    }

    /** @return array<string, string> */
    public function presetOptions(): array
    {
        $options = [];
        foreach (config('fest_participation_presets', []) as $key => $preset) {
            $options[$key] = $preset['label'] ?? $key;
        }

        return $options;
    }

    public function resolveForEvent(FestEvent $event, ?string $classGroup = null): array
    {
        $policy = FestParticipationPolicy::where('event_id', $event->id)
            ->where('is_active', true)
            ->when($classGroup, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('class_group', $classGroup)
                ->orWhereNull('class_group')))
            ->orderByRaw('class_group IS NULL')
            ->first();

        if ($policy) {
            return $policy->toLimitArray();
        }

        if ($event->state_program_id) {
            $program = FestStateProgram::find($event->state_program_id);
            $level = $event->level_round ?? 'sahodaya';
            $levelPolicy = $program?->level_policies[$level] ?? null;
            if (is_array($levelPolicy)) {
                return $this->mergePreset($levelPolicy);
            }
        }

        $presetKey = match ($event->level_round) {
            'school' => 'cksc_school_kalakriti',
            default => 'cksc_sahodaya_cluster',
        };

        return $this->mergePreset(['preset_key' => $presetKey]);
    }

    /** @param array<string, mixed> $data */
    public function mergePreset(array $data): array
    {
        $preset = isset($data['preset_key'])
            ? $this->preset($data['preset_key'])
            : [];

        unset($preset['label']);

        return array_merge($preset, array_filter($data, fn ($v) => $v !== null && $v !== ''));
    }

    public function applyPresetToEvent(FestEvent $event, string $presetKey, ?string $classGroup = null): FestParticipationPolicy
    {
        $preset = $this->preset($presetKey);

        return FestParticipationPolicy::updateOrCreate(
            ['event_id' => $event->id, 'class_group' => $classGroup],
            array_merge([
                'tenant_id' => $event->tenant_id,
                'scope' => 'event',
                'level_round' => $event->level_round ?? 'sahodaya',
                'preset_key' => $presetKey,
                'is_active' => true,
            ], array_intersect_key($preset, array_flip([
                'max_onstage_per_school', 'max_offstage_per_school', 'max_group_per_school',
                'max_onstage_per_student', 'max_offstage_per_student', 'max_group_per_student',
                'max_total_per_student', 'one_entry_per_item_per_school',
                'count_submitted_registrations', 'exclude_standbys_from_limits',
            ])))
        );
    }

    public function copyFromStateProgram(FestEvent $event, FestStateProgram $program): void
    {
        $level = $event->level_round ?? 'sahodaya';
        $levelPolicy = $program->level_policies[$level] ?? null;

        if (! is_array($levelPolicy)) {
            $presetKey = $level === 'school' ? 'cksc_school_kalakriti' : 'cksc_sahodaya_cluster';
            $this->applyPresetToEvent($event, $presetKey);

            return;
        }

        $presetKey = $levelPolicy['preset_key'] ?? null;
        $attrs = $this->mergePreset($levelPolicy);

        FestParticipationPolicy::updateOrCreate(
            ['event_id' => $event->id, 'class_group' => null],
            array_merge([
                'tenant_id' => $event->tenant_id,
                'scope' => 'event',
                'level_round' => $level,
                'preset_key' => $presetKey,
                'is_active' => true,
            ], array_intersect_key($attrs, array_flip([
                'max_onstage_per_school', 'max_offstage_per_school', 'max_group_per_school',
                'max_onstage_per_student', 'max_offstage_per_student', 'max_group_per_student',
                'max_total_per_student', 'one_entry_per_item_per_school',
                'count_submitted_registrations', 'exclude_standbys_from_limits',
                'require_fee_before_approval',
            ])))
        );
    }
}
