<?php

namespace App\Support;

final class IdCardDiePreset
{
    private const KOCHI_METRO_SAHODAYA_ID = 'b7f9b005-9f08-4833-8c02-8767a440ad01';

    /** @return list<array<string, mixed>> */
    public static function optionsForTenant(string $tenantId): array
    {
        return $tenantId === self::KOCHI_METRO_SAHODAYA_ID
            ? self::options()
            : [];
    }

    /**
     * Measured physical sheet layouts that can be applied to an individual
     * ID-card template without changing its artwork or field arrangement.
     *
     * @return list<array<string, mixed>>
     */
    public static function options(): array
    {
        return [
            [
                'key' => 'badge-die-10-up-19x13',
                'label' => 'Badge die — 10 cards',
                'description' => '19 × 13 inch sheet · 5 × 2 portrait cards · 90 × 140 mm each',
                'card_width_mm' => 90,
                'card_height_mm' => 140,
                'cards_per_page' => 10,
                'page_width_mm' => 482.6,
                'page_height_mm' => 330.2,
                'grid' => [
                    'cols' => 5,
                    'rows' => 2,
                    'first_col_center_mm' => 61.3,
                    'first_row_center_mm' => 95.1,
                    'col_pitch_mm' => 90,
                    'row_pitch_mm' => 140,
                ],
            ],
        ];
    }
}
