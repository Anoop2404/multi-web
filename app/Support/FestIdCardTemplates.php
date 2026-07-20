<?php

namespace App\Support;

class FestIdCardTemplates
{
    public const STANDARD = 'standard';

    public const PREMIUM = 'premium';

    /** Compact 4x2 grid card size for print/PDF (mm). */
    public const CARD_WIDTH_MM = 96;

    public const CARD_HEIGHT_MM = 66;

    public const CARDS_PER_PAGE = 8;

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
