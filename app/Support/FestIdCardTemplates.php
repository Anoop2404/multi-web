<?php

namespace App\Support;

class FestIdCardTemplates
{
    public const STANDARD = 'standard';

    public const PREMIUM = 'premium';

    /**
     * ID card size, 4 per A4 LANDSCAPE page (2 columns x 2 rows).
     * Landscape gives ~285mm of usable width per page instead of ~198mm in
     * portrait, so the card can stay properly landscape-shaped (wider than
     * tall, ~1.6:1 — close to a standard CR80 card) at a size generous
     * enough for names to fit on one line without the mid-word wrapping a
     * narrower portrait-shaped card caused.
     *
     * Height is 86mm, not 92mm: the "sections" (grouped-by-head) render
     * path also prints a section-title line above the 2x2 grid on every
     * page. At 92mm, 2 rows + gutters (196mm) plus that title line (~8mm)
     * exceeded the 198mm landscape content height by a few mm — just
     * enough to push the second row onto its own near-empty page, which is
     * exactly the "one blank page, then a page with only 2 cards" pattern
     * that showed up in print preview. 86mm leaves real headroom:
     * 2 x 86mm + (4mm gutter x 3 gaps) = 184mm, + the section title, still
     * comfortably under 198mm.
     */
    public const CARD_WIDTH_MM = 138;

    public const CARD_HEIGHT_MM = 86;

    public const CARDS_PER_PAGE = 4;

    /** @return list<string> */
    public static function ids(): array
    {
        return [self::STANDARD, self::PREMIUM];
    }

    public static function sheetView(?string $template): string
    {
        return match ($template) {
            self::PREMIUM => 'fest.id-cards.premium-sheet',
            default       => 'fest.id-cards.sheet',
        };
    }

    public static function normalize(?string $template): string
    {
        return in_array($template, self::ids(), true) ? $template : self::STANDARD;
    }
}
