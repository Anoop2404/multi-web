<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * Builds standardized downloadable-file names: {purpose}_{subject}_{date}.{ext}
 *
 * Segment separator is "_"; within a segment words are hyphenated (kebab-case).
 * This keeps the three parts visually and programmatically distinguishable,
 * unlike the previous all-hyphen scheme ("{slug}-mark-entry-status.pdf") where
 * you can't tell where the subject ends and the report type begins.
 *
 * See docs/EXPORT_FILENAME_STANDARDIZATION_PLAN.md for the full rationale and
 * migration plan this class exists to support.
 */
class ReportFilename
{
    /**
     * @param  string  $purpose  What the file is, e.g. "registration-list", "id-cards-event-pass".
     * @param  string  $subjectTitle  The event/exam/program (or school/Sahodaya) title. Slugged and
     *                                truncated to 40 chars.
     * @param  DateTimeInterface|null  $date  The date this file is "for" — prefer the event's own
     *                                        start date over today's date wherever one exists, so
     *                                        filenames stay distinguishable across recurring events
     *                                        with the same title in different years. Defaults to now().
     * @param  array<int, string|int|null>  $extra  Additional identifying segments appended in order
     *                                              (e.g. school name, item id, judge id). Null/empty
     *                                              entries are skipped.
     * @param  string  $ext  File extension, without a leading dot.
     */
    public static function build(
        string $purpose,
        string $subjectTitle,
        ?DateTimeInterface $date = null,
        array $extra = [],
        string $ext = 'pdf',
    ): string {
        $parts = array_filter([
            static::slug($purpose),
            static::slug($subjectTitle, 40),
            ...array_map(
                fn ($value) => $value === null || $value === '' ? null : static::slug((string) $value),
                $extra,
            ),
            static::formatDate($date),
        ], fn ($part) => $part !== null && $part !== '');

        return implode('_', $parts).'.'.ltrim($ext, '.');
    }

    /**
     * build() for a fest event, naming it so a phase/region leg stays identifiable: the
     * Sahodaya's own name is dropped from the front of the title (it's the same on every
     * file they download), the root fest's title becomes the subject, and a leg's own name
     * (its title after the root's — "Sargadhara — Tirur Region", "Digi Fest") is added as
     * its own segment. Plain build() cut the full leg title at 40 characters, so every leg
     * of "Malappuram Central Sahodaya Kalotsavam 2026-27 — …" came out as the same
     * "malappuram-central-sahodaya-kalotsavam-2".
     *
     * @param  array<int, string|int|null>  $extra  Appended after the leg name, as in build().
     */
    public static function buildForEvent(
        string $purpose,
        \App\Models\FestEvent $event,
        array $extra = [],
        string $ext = 'pdf',
        ?string $organizationName = null,
    ): string {
        $withoutOrganization = function (string $title) use ($organizationName): string {
            if (! $organizationName) {
                return $title;
            }
            $stripped = preg_replace('/^'.preg_quote(trim($organizationName), '/').'[\s\x{2014}\-:]*/iu', '', $title);

            return trim((string) $stripped) !== '' ? trim((string) $stripped) : $title;
        };

        $root = $event->rootEvent();
        $legName = null;
        if ($root && $root->id !== $event->id) {
            $legName = str_starts_with($event->title, $root->title)
                ? trim((string) preg_replace('/^[\s\x{2014}\-:]+/u', '', substr($event->title, strlen($root->title))))
                : $withoutOrganization($event->title);
        }

        return static::build(
            $purpose,
            $withoutOrganization(($root ?? $event)->title),
            $event->event_start ?? $root?->event_start,
            [$legName ?: null, ...$extra],
            $ext,
        );
    }

    protected static function slug(string $value, ?int $limit = null): string
    {
        $slug = str($value)->slug();

        if ($limit !== null) {
            $slug = $slug->limit($limit, '');
        }

        return (string) $slug;
    }

    protected static function formatDate(?DateTimeInterface $date): string
    {
        if ($date instanceof CarbonInterface) {
            return $date->format('Y-m-d');
        }

        if ($date instanceof DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return now()->format('Y-m-d');
    }
}
