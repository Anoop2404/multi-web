<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestStateProgram;

class FestEffectiveSettingsResolverService
{
    /**
     * Resolve effective settings for a given level and event/program.
     *
     * Hierarchy:
     * Platform Defaults
     *   -> State Program settings for requested level ('sahodaya' or 'state')
     *   -> Event-level allowed overrides
     *
     * @return array<string, mixed>
     */
    public function resolve(string $level, ?FestStateProgram $program = null, ?FestEvent $event = null): array
    {
        $platformDefaults = $this->getPlatformDefaults($level);

        $programSettings = [];
        if ($program) {
            $programSettings = $program->level_event_settings[$level] ?? [];
        }

        $eventOverrides = [];
        if ($event && $level === 'sahodaya' && is_array($event->settings)) {
            $eventOverrides = $event->settings;
        }

        $merged = array_merge($platformDefaults, $programSettings, $eventOverrides);

        return [
            'settings'         => $merged,
            'settings_version' => $program?->settings_version ?? 1,
            'source'           => $event ? 'event_override' : ($program ? 'state_program' : 'platform_defaults'),
        ];
    }

    /**
     * Default platform settings structure for Sahodaya and State levels.
     *
     * @return array<string, mixed>
     */
    public function getPlatformDefaults(string $level): array
    {
        if ($level === 'state') {
            return [
                'registration_open'          => null,
                'registration_close'         => null,
                'max_total_per_student'      => 3,
                'max_onstage_per_student'    => 2,
                'max_offstage_per_student'   => 3,
                'max_group_per_student'      => 2,
                'individual_fee_amount'      => 500,
                'team_fee_amount'            => 1000,
            ];
        }

        return [
            'registration_open'          => null,
            'registration_close'         => null,
            'max_total_per_student'      => 5,
            'max_onstage_per_student'    => 3,
            'max_offstage_per_student'   => 5,
            'max_group_per_student'      => 2,
            'individual_fee_amount'      => 100,
            'team_fee_amount'            => 500,
        ];
    }
}
