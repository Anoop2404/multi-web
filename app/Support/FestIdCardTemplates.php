<?php

namespace App\Support;

class FestIdCardTemplates
{
    public const STANDARD = 'standard';

    public const PREMIUM = 'premium';

    public const PASS = 'pass';

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

    /**
     * "Participant Pass" layout — a school-ID-style card that lists every item a
     * student is registered for on one card, instead of one card per registration.
     * 4 cards per A4 LANDSCAPE page (2x2), same proven geometry as the Premium
     * template above (138x86mm cards) — was previously 10-per-page at CR80 credit-
     * card size (85.6x54mm) in portrait; every internal spacing/font-size in the
     * pass-sheet/pass-card templates was scaled up ~1.6x (138/85.6, 86/54) to match,
     * not just the outer card box.
     */
    public const PASS_CARD_WIDTH_MM = self::CARD_WIDTH_MM;

    public const PASS_CARD_HEIGHT_MM = self::CARD_HEIGHT_MM;

    public const PASS_CARDS_PER_PAGE = self::CARDS_PER_PAGE;

    /** @return list<string> */
    public static function ids(): array
    {
        return [self::STANDARD, self::PREMIUM, self::PASS];
    }

    public static function sheetView(?string $template): string
    {
        return match ($template) {
            self::PREMIUM => 'fest.id-cards.premium-sheet',
            self::PASS    => 'fest.id-cards.pass-sheet',
            default       => 'fest.id-cards.sheet',
        };
    }

    public static function normalize(?string $template): string
    {
        return in_array($template, self::ids(), true) ? $template : self::STANDARD;
    }
}
