<?php

namespace App\Support;

class FestIdCardTemplates
{
    public const STANDARD = 'standard';

    public const PREMIUM = 'premium';

    /**
     * ID card size, 4 per A4 page (2 columns x 2 rows). Height is
     * deliberately large — at 62mm the 2x2 block only filled ~45% of the
     * page height, leaving a huge blank strip at the bottom of every
     * printed sheet. 130mm fills the page properly (2 x 130mm + gutters
     * ≈ the full usable A4 height).
     */
    /** 96mm x 2 + (2mm border-spacing x 3 gaps) = 198mm, exactly the A4 content width after margins. */
    public const CARD_WIDTH_MM = 96;

    public const CARD_HEIGHT_MM = 130;

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
