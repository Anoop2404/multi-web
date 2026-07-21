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
     * tall, ~1.5:1) at a size generous enough for names to fit on one line
     * without the awkward mid-word wrapping a narrower portrait-shaped card
     * caused. 2 x 138mm + (2mm gutter x 3 gaps) = 282mm ≤ 285mm content
     * width; 2 x 92mm + (3mm gutter x 3 gaps) = 193mm ≤ 198mm content
     * height (A4 landscape content box after the 6mm @page margin).
     */
    public const CARD_WIDTH_MM = 138;

    public const CARD_HEIGHT_MM = 92;

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
