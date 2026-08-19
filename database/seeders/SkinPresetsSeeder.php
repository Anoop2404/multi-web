<?php

namespace Database\Seeders;

use App\Models\SkinPreset;
use Illuminate\Database\Seeder;

class SkinPresetsSeeder extends Seeder
{
    public function run(): void
    {
        $presets = [
            [
                'name'          => 'Indigo Classic',
                'slug'          => 'indigo-classic',
                'description'   => 'Professional indigo blue with clean white accents. Great for CBSE schools.',
                'display_order' => 1,
                'theme'         => [
                    'primary'         => '#4f46e5',
                    'secondary'       => '#3730a3',
                    'accent_color'    => '#f59e0b',
                    'font_heading'    => 'Poppins',
                    'font_body'       => 'Inter',
                    'border_radius'   => '0.5rem',
                    'navbar_style'    => 'default',
                    'footer_style'    => 'three-column',
                ],
            ],
            [
                'name'          => 'Emerald Green',
                'slug'          => 'emerald-green',
                'description'   => 'Fresh emerald green — ideal for nature-oriented and eco-conscious schools.',
                'display_order' => 2,
                'theme'         => [
                    'primary'         => '#059669',
                    'secondary'       => '#047857',
                    'accent_color'    => '#f59e0b',
                    'font_heading'    => 'Nunito',
                    'font_body'       => 'Inter',
                    'border_radius'   => '0.75rem',
                    'navbar_style'    => 'default',
                    'footer_style'    => 'three-column',
                ],
            ],
            [
                'name'          => 'Royal Blue',
                'slug'          => 'royal-blue',
                'description'   => 'Deep royal blue with gold accent. Conveys tradition and excellence.',
                'display_order' => 3,
                'theme'         => [
                    'primary'         => '#1d4ed8',
                    'secondary'       => '#1e3a8a',
                    'accent_color'    => '#fbbf24',
                    'font_heading'    => 'Merriweather',
                    'font_body'       => 'Lato',
                    'border_radius'   => '0.25rem',
                    'navbar_style'    => 'dark',
                    'footer_style'    => 'three-column',
                ],
            ],
            [
                'name'          => 'Crimson Pride',
                'slug'          => 'crimson-pride',
                'description'   => 'Bold crimson red with warm gold. Strong and energetic.',
                'display_order' => 4,
                'theme'         => [
                    'primary'         => '#dc2626',
                    'secondary'       => '#991b1b',
                    'accent_color'    => '#f59e0b',
                    'font_heading'    => 'Poppins',
                    'font_body'       => 'Inter',
                    'border_radius'   => '0.5rem',
                    'navbar_style'    => 'dark',
                    'footer_style'    => 'three-column',
                ],
            ],
            [
                'name'          => 'Teal Modern',
                'slug'          => 'teal-modern',
                'description'   => 'Contemporary teal with clean lines. Great for progressive schools.',
                'display_order' => 5,
                'theme'         => [
                    'primary'         => '#0d9488',
                    'secondary'       => '#0f766e',
                    'accent_color'    => '#f97316',
                    'font_heading'    => 'Nunito',
                    'font_body'       => 'Inter',
                    'border_radius'   => '1rem',
                    'navbar_style'    => 'default',
                    'footer_style'    => 'three-column',
                ],
            ],
            [
                'name'          => 'Purple Prestige',
                'slug'          => 'purple-prestige',
                'description'   => 'Rich purple with silver accent. Sahodaya cluster default.',
                'display_order' => 6,
                'theme'         => [
                    'primary'         => '#7c3aed',
                    'secondary'       => '#5b21b6',
                    'accent_color'    => '#f59e0b',
                    'font_heading'    => 'Poppins',
                    'font_body'       => 'Inter',
                    'border_radius'   => '0.5rem',
                    'navbar_style'    => 'dark',
                    'footer_style'    => 'three-column',
                ],
            ],
            [
                'name'          => 'Forest Green',
                'slug'          => 'forest-green',
                'description'   => 'Deep forest green with amber accent.',
                'display_order' => 7,
                'theme'         => [
                    'primary'         => '#166534',
                    'secondary'       => '#22c55e',
                    'accent_color'    => '#fbbf24',
                    'font_heading'    => 'Inter',
                    'font_body'       => 'Inter',
                    'border_radius'   => '0.75rem',
                    'navbar_style'    => 'default',
                    'footer_style'    => 'three-column',
                ],
            ],
            [
                'name'          => 'Navy & Gold',
                'slug'          => 'navy-gold',
                'description'   => 'Navy and gold — formal, institutional feel.',
                'display_order' => 8,
                'theme'         => [
                    'primary'         => '#15224D',
                    'secondary'       => '#D9AF4B',
                    'accent_color'    => '#D9AF4B',
                    'font_heading'    => 'Roboto',
                    'font_body'       => 'Roboto',
                    'border_radius'   => '0.25rem',
                    'navbar_style'    => 'dark',
                    'footer_style'    => 'three-column',
                ],
            ],
            [
                'name'          => 'Modern Slate',
                'slug'          => 'modern-slate',
                'description'   => 'Understated slate grey with a sky-blue accent.',
                'display_order' => 9,
                'theme'         => [
                    'primary'         => '#1e293b',
                    'secondary'       => '#475569',
                    'accent_color'    => '#38bdf8',
                    'font_heading'    => 'Inter',
                    'font_body'       => 'Inter',
                    'border_radius'   => '0.5rem',
                    'navbar_style'    => 'dark',
                    'footer_style'    => 'minimal',
                ],
            ],
        ];

        foreach ($presets as $preset) {
            SkinPreset::updateOrCreate(
                ['slug' => $preset['slug']],
                array_merge($preset, ['is_active' => true])
            );
        }

        $this->command->info('Skin presets seeded: ' . count($presets) . ' presets.');
    }
}
