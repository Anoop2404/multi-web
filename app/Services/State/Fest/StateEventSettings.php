<?php

namespace App\Services\State\Fest;

use App\Models\State\StateFestEvent;
use Carbon\CarbonImmutable;

/**
 * Phase 3 of the State Kalotsav module — event settings and the windows that gate the workflow.
 *
 * Stored in state_fest_events.settings rather than a column each: these are event-scoped policy
 * that differs year to year, and a table of thirty nullable columns would be worse to read and
 * worse to migrate. The two that already earned columns — results_published, scoring_locked — stay
 * where they are, because they gate writes on a hot path and are queried, not just read.
 *
 * The windows are the point. A qualifier submission window that nothing enforces is documentation;
 * here, closing it actually refuses submissions, which is what a State office needs on the day the
 * deadline passes.
 */
class StateEventSettings
{
    public const WINDOW_QUALIFIER = 'qualifier';

    public const WINDOW_SCRUTINY = 'scrutiny';

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'contact_name'  => null,
            'contact_phone' => null,
            'contact_email' => null,
            'venue_summary' => null,

            // Windows. Null at either end means open-ended on that side; null at both means the
            // window is not being used and the workflow is governed by event status alone.
            'qualifier_opens_at'  => null,
            'qualifier_closes_at' => null,
            'scrutiny_opens_at'   => null,
            'scrutiny_closes_at'  => null,

            // Publication controls, separate from results_published so a State office can stage
            // what the public sees without unpublishing results internally.
            'public_schedule_visible' => false,
            'public_results_visible'  => false,
            'public_ranking_visible'  => false,

            // Locking. registrations_locked stops new registrations without touching scoring.
            'registrations_locked' => false,

            'notify_on_submission' => true,
            'notify_on_approval'   => true,
        ];
    }

    /** @return array<string, mixed> */
    public function all(StateFestEvent $event): array
    {
        return array_merge(self::defaults(), $event->settings ?? []);
    }

    public function get(StateFestEvent $event, string $key): mixed
    {
        return $this->all($event)[$key] ?? null;
    }

    /**
     * Merge changes in. Unknown keys are dropped rather than stored, so a typo in a form field
     * cannot quietly become a setting nothing reads.
     *
     * @param  array<string, mixed>  $changes
     */
    public function update(StateFestEvent $event, array $changes): StateFestEvent
    {
        $allowed = array_intersect_key($changes, self::defaults());

        $event->forceFill(['settings' => array_merge($this->all($event), $allowed)])->save();

        return $event->fresh();
    }

    /**
     * Whether a window is open now.
     *
     * An unset window is open: a State office that has not configured dates should not find its
     * workflow silently shut. The window closes only when it has been given a closing date that
     * has passed.
     */
    public function windowIsOpen(StateFestEvent $event, string $window, ?CarbonImmutable $at = null): bool
    {
        $at ??= CarbonImmutable::now();
        $settings = $this->all($event);

        $opens = $settings["{$window}_opens_at"] ?? null;
        $closes = $settings["{$window}_closes_at"] ?? null;

        if ($opens && $at->lt(CarbonImmutable::parse($opens))) {
            return false;
        }

        if ($closes && $at->gt(CarbonImmutable::parse($closes))) {
            return false;
        }

        return true;
    }

    /** Why a window is shut, phrased for the Sahodaya on the other end of it. */
    public function windowClosedReason(StateFestEvent $event, string $window, ?CarbonImmutable $at = null): ?string
    {
        if ($this->windowIsOpen($event, $window, $at)) {
            return null;
        }

        $at ??= CarbonImmutable::now();
        $settings = $this->all($event);
        $opens = $settings["{$window}_opens_at"] ?? null;
        $closes = $settings["{$window}_closes_at"] ?? null;
        $label = $window === self::WINDOW_QUALIFIER ? 'Qualifier submission' : 'Scrutiny';

        if ($opens && $at->lt(CarbonImmutable::parse($opens))) {
            return "{$label} opens on ".CarbonImmutable::parse($opens)->format('d M Y, H:i').'.';
        }

        return "{$label} closed on ".CarbonImmutable::parse($closes)->format('d M Y, H:i').'.';
    }

    /** @return array<string, mixed> the shape the Settings screen renders */
    public function forDisplay(StateFestEvent $event): array
    {
        $settings = $this->all($event);

        return $settings + [
            'qualifier_window_open' => $this->windowIsOpen($event, self::WINDOW_QUALIFIER),
            'scrutiny_window_open'  => $this->windowIsOpen($event, self::WINDOW_SCRUTINY),
            'qualifier_window_note' => $this->windowClosedReason($event, self::WINDOW_QUALIFIER),
            'scrutiny_window_note'  => $this->windowClosedReason($event, self::WINDOW_SCRUTINY),
        ];
    }
}
