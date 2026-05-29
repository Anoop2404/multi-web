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
                    'primary_dark'    => '#3730a3',
                    'accent'          => '#f59e0b',
                    'background'      => '#ffffff',
                    'text'            => '#111827',
                    'font_heading'    => 'Poppins',
                    'font_body'       => 'Inter',
                    'border_radius'   => '0.5rem',
                    'navbar_style'    => 'default',
                ],
            ],
            [
                'name'          => 'Emerald Green',
                'slug'          => 'emerald-green',
                'description'   => 'Fresh emerald green — ideal for nature-oriented and eco-conscious schools.',
                'display_order' => 2,
                'theme'         => [
                    'primary'         => '#059669',
                    'primary_dark'    => '#047857',
                    'accent'          => '#f59e0b',
                    'background'      => '#ffffff',
                    'text'            => '#111827',
                    'font_heading'    => 'Nunito',
                    'font_body'       => 'Inter',
                    'border_radius'   => '0.75rem',
                    'navbar_style'    => 'default',
                ],
            ],
            [
                'name'          => 'Royal Blue',
                'slug'          => 'royal-blue',
                'description'   => 'Deep royal blue with gold accent. Conveys tradition and excellence.',
                'display_order' => 3,
                'theme'         => [
                    'primary'         => '#1d4ed8',
                    'primary_dark'    => '#1e3a8a',
                    'accent'          => '#fbbf24',
                    'background'      => '#ffffff',
                    'text'            => '#1f2937',
                    'font_heading'    => 'Merriweather',
                    'font_body'       => 'Lato',
                    'border_radius'   => '0.25rem',
                    'navbar_style'    => 'dark',
                ],
            ],
            [
                'name'          => 'Crimson Pride',
                'slug'          => 'crimson-pride',
                'description'   => 'Bold crimson red with warm gold. Strong and energetic.',
                'display_order' => 4,
                'theme'         => [
                    'primary'         => '#dc2626',
                    'primary_dark'    => '#991b1b',
                    'accent'          => '#f59e0b',
                    'background'      => '#ffffff',
                    'text'            => '#111827',
                    'font_heading'    => 'Poppins',
                    'font_body'       => 'Inter',
                    'border_radius'   => '0.5rem',
                    'navbar_style'    => 'dark',
                ],
            ],
            [
                'name'          => 'Teal Modern',
                'slug'          => 'teal-modern',
                'description'   => 'Contemporary teal with clean lines. Great for progressive schools.',
                'display_order' => 5,
                'theme'         => [
                    'primary'         => '#0d9488',
                    'primary_dark'    => '#0f766e',
                    'accent'          => '#f97316',
                    'background'      => '#ffffff',
                    'text'            => '#111827',
                    'font_heading'    => 'Nunito',
                    'font_body'       => 'Inter',
                    'border_radius'   => '1rem',
                    'navbar_style'    => 'default',
                ],
            ],
            [
                'name'          => 'Purple Prestige',
                'slug'          => 'purple-prestige',
                'description'   => 'Rich purple with silver accent. Sahodaya cluster default.',
                'display_order' => 6,
                'theme'         => [
                    'primary'         => '#7c3aed',
                    'primary_dark'    => '#5b21b6',
                    'accent'          => '#e2e8f0',
                    'background'      => '#ffffff',
                    'text'            => '#1e1b4b',
                    'font_heading'    => 'Poppins',
                    'font_body'       => 'Inter',
                    'border_radius'   => '0.5rem',
                    'navbar_style'    => 'dark',
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
