<?php

namespace App\Support;

/**
 * Squad / team roster rules for fest_event_items (team games, relays, group items).
 *
 * criteria_json keys:
 *   min_playing  — players required on field/court at start
 *   max_playing  — max on field (usually same as min_playing)
 *   max_subs     — substitute slots allowed in squad
 *   max_squad    — total roster cap (playing + subs + standbys)
 *   min_squad    — minimum students to register (defaults to min_playing)
 *   standbys     — extra standbys (no fee, no certificate per Tarang rules)
 */
class FestTeamSquadRules
{
    public function __construct(
        public ?int $minPlaying = null,
        public ?int $maxPlaying = null,
        public ?int $maxSubs = null,
        public ?int $maxSquad = null,
        public ?int $minSquad = null,
        public ?int $standbys = null,
    ) {}

    public static function fromItem(\App\Models\FestEventItem $item): ?self
    {
        if (! in_array($item->participant_type, ['team', 'group'], true)) {
            return null;
        }

        $c = $item->criteria_json ?? [];

        $minPlaying = isset($c['min_playing']) ? (int) $c['min_playing'] : null;
        $maxPlaying = isset($c['max_playing']) ? (int) $c['max_playing'] : $minPlaying;
        $maxSubs = isset($c['max_subs']) ? (int) $c['max_subs'] : null;
        $maxSquad = isset($c['max_squad']) ? (int) $c['max_squad'] : ($item->max_group_size ? (int) $item->max_group_size : null);
        $minSquad = isset($c['min_squad']) ? (int) $c['min_squad'] : ($item->min_group_size ? (int) $item->min_group_size : $minPlaying);
        $standbys = isset($c['standbys']) ? (int) $c['standbys'] : null;

        if (! $minSquad && ! $maxSquad && ! $minPlaying) {
            if ($item->min_group_size || $item->max_group_size) {
                return new self(
                    minPlaying: $item->min_group_size,
                    maxPlaying: $item->min_group_size,
                    maxSquad: $item->max_group_size,
                    minSquad: $item->min_group_size,
                );
            }

            return null;
        }

        return new self($minPlaying, $maxPlaying, $maxSubs, $maxSquad, $minSquad, $standbys);
    }

    /** @return array<string, int|null> */
    public function toCriteriaArray(): array
    {
        return array_filter([
            'min_playing' => $this->minPlaying,
            'max_playing' => $this->maxPlaying,
            'max_subs'    => $this->maxSubs,
            'max_squad'   => $this->maxSquad,
            'min_squad'   => $this->minSquad,
            'standbys'    => $this->standbys,
        ], fn ($v) => $v !== null);
    }

    public function summary(): string
    {
        $parts = [];

        if ($this->minPlaying) {
            $line = $this->maxPlaying && $this->maxPlaying !== $this->minPlaying
                ? "{$this->minPlaying}–{$this->maxPlaying} on field"
                : "{$this->minPlaying} on field";
            $parts[] = $line;
        }

        if ($this->maxSubs !== null) {
            $parts[] = "up to {$this->maxSubs} subs";
        }

        if ($this->standbys) {
            $parts[] = "{$this->standbys} standby(s)";
        }

        if ($this->minSquad && $this->maxSquad) {
            $parts[] = "register {$this->minSquad}–{$this->maxSquad} students";
        } elseif ($this->maxSquad) {
            $parts[] = "max {$this->maxSquad} in squad";
        } elseif ($this->minSquad) {
            $parts[] = "min {$this->minSquad} students";
        }

        return $parts ? implode(' · ', $parts) : 'Team / group item';
    }

    public function validateCount(int $count): ?string
    {
        if ($this->minSquad && $count < $this->minSquad) {
            return "This item requires at least {$this->minSquad} participant(s) in the squad ({$this->summary()}).";
        }

        if ($this->maxSquad && $count > $this->maxSquad) {
            return "This item allows at most {$this->maxSquad} participant(s) including substitutes ({$this->summary()}).";
        }

        if ($this->minPlaying && $count < $this->minPlaying && ! $this->minSquad) {
            return "At least {$this->minPlaying} playing member(s) required.";
        }

        return null;
    }

    /**
     * Build criteria + min/max columns from admin input.
     *
     * @return array{criteria_json: array, min_group_size: ?int, max_group_size: ?int}
     */
    public static function mergeIntoItem(array $input): array
    {
        $rules = new self(
            minPlaying: isset($input['min_playing']) ? (int) $input['min_playing'] : null,
            maxPlaying: isset($input['max_playing']) ? (int) $input['max_playing'] : null,
            maxSubs: isset($input['max_subs']) ? (int) $input['max_subs'] : null,
            maxSquad: isset($input['max_squad']) ? (int) $input['max_squad'] : null,
            minSquad: isset($input['min_squad']) ? (int) $input['min_squad'] : null,
            standbys: isset($input['standbys']) ? (int) $input['standbys'] : null,
        );

        if ($rules->maxSquad && $rules->minPlaying && ! $rules->maxSubs && ! $rules->minSquad) {
            $rules->maxSubs = max(0, $rules->maxSquad - $rules->minPlaying);
            $rules->minSquad = $rules->minSquad ?? $rules->minPlaying;
        }

        return [
            'criteria_json'   => $rules->toCriteriaArray(),
            'min_group_size'  => $rules->minSquad ?? $rules->minPlaying,
            'max_group_size'  => $rules->maxSquad,
        ];
    }
}
