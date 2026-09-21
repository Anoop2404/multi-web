<?php

namespace Database\Seeders;

use App\Models\IdCardTemplate;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Installs the two approved Malappuram Kalotsav student ID-card designs.
 *
 * The background artwork is copied from database/seeders/assets and every
 * editable overlay remains in layout_json, including the automatic 1–7 item
 * list. Every run creates a fresh pair of templates; subsequent pairs receive
 * a numbered "Copy" suffix so they are easy to distinguish in the builder.
 *
 * Usage: php artisan db:seed --class=MalappuramKalotsavIdCardTemplatesSeeder
 */
class MalappuramKalotsavIdCardTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        if (tenancy()->initialized) {
            $this->seedForTenant((string) tenant('id'));

            return;
        }

        $sahodaya = Tenant::query()
            ->where('type', 'sahodaya')
            ->where(function ($query) {
                $query->where('subdomain', 'malappuram')
                    ->orWhere('domain', 'malappuramsahodaya.test')
                    ->orWhere('name', 'Malappuram Sahodaya');
            })
            ->first();

        if (! $sahodaya) {
            $this->command?->warn('MalappuramKalotsavIdCardTemplatesSeeder: Malappuram Sahodaya was not found.');

            return;
        }

        $sahodaya->run(fn () => $this->seedForTenant((string) $sahodaya->id));
    }

    public function seedForTenant(string $tenantId): void
    {
        $copyNumber = $this->nextCopyNumber($tenantId);
        $createdIds = [];

        foreach ($this->templates() as $definition) {
            $title = $copyNumber === 1
                ? $definition['title']
                : $definition['title']." (Copy {$copyNumber})";
            $backgroundFilename = $this->copyFilename($definition['background_filename'], $copyNumber);
            $backgroundPath = $this->installBackground(
                $tenantId,
                $definition['background_asset'],
                $backgroundFilename,
            );

            if ($definition['is_active']) {
                $this->deactivateMatchingScope($tenantId, $definition['audience']);
            }

            $created = IdCardTemplate::create([
                'tenant_id' => $tenantId,
                'title' => $title,
                'event_id' => null,
                'item_id' => null,
                'audience' => $definition['audience'],
                'background_path' => $backgroundPath,
                'card_width_mm' => $definition['card_width_mm'],
                'card_height_mm' => $definition['card_height_mm'],
                'cards_per_page' => 10,
                'page_width_mm' => 480.06,
                'page_height_mm' => 314.96,
                'grid_json' => $definition['grid_json'],
                'layout_json' => $definition['layout_json'],
                'is_active' => $definition['is_active'],
            ]);

            $createdIds[] = $created->id;
        }

        $this->command?->info(
            'Created two new Malappuram Kalotsav student ID-card templates (IDs: '.implode(', ', $createdIds).').'
        );
    }

    private function nextCopyNumber(string $tenantId): int
    {
        $baseTitle = $this->templates()[0]['title'];
        $highestCopy = 0;

        IdCardTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($baseTitle) {
                $query->where('title', $baseTitle)
                    ->orWhere('title', 'like', $baseTitle.' (Copy %');
            })
            ->pluck('title')
            ->each(function (string $title) use ($baseTitle, &$highestCopy) {
                if ($title === $baseTitle) {
                    $highestCopy = max($highestCopy, 1);

                    return;
                }

                if (preg_match('/^'.preg_quote($baseTitle, '/').' \(Copy (\d+)\)$/u', $title, $matches)) {
                    $highestCopy = max($highestCopy, (int) $matches[1]);
                }
            });

        return $highestCopy + 1;
    }

    private function copyFilename(string $filename, int $copyNumber): string
    {
        if ($copyNumber === 1) {
            return $filename;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        return $basename."-copy-{$copyNumber}.{$extension}";
    }

    private function deactivateMatchingScope(string $tenantId, ?string $audience): void
    {
        $query = IdCardTemplate::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('event_id')
            ->whereNull('item_id');

        $audience === null
            ? $query->whereNull('audience')
            : $query->where('audience', $audience);

        $query->update(['is_active' => false]);
    }

    private function installBackground(string $tenantId, string $asset, string $filename): string
    {
        $source = database_path('seeders/assets/id-card-templates/'.$asset);
        if (! is_file($source)) {
            throw new \RuntimeException("ID-card template background is missing: {$source}");
        }

        $relativePath = "sahodaya/{$tenantId}/id-card-templates/backgrounds/{$filename}";
        $destination = base_path('storage/app/public/'.$relativePath);
        File::ensureDirectoryExists(dirname($destination));
        File::copy($source, $destination);

        return $relativePath;
    }

    /** @return list<array<string, mixed>> */
    private function templates(): array
    {
        return [
            [
                'title' => 'Kalotsav 2025-26 Student ID (die-cut)',
                'audience' => null,
                'is_active' => true,
                'background_asset' => 'kalotsav-student-id-template-1.png',
                'background_filename' => 'kalotsav-student-id-template-1.png',
                'card_width_mm' => 90,
                'card_height_mm' => 140,
                'grid_json' => [
                    'cols' => 5,
                    'rows' => 2,
                    'first_col_center_mm' => 49.758,
                    'first_row_center_mm' => 87.96,
                    'col_pitch_mm' => 92.979,
                    'row_pitch_mm' => 138.969,
                ],
                'layout_json' => $this->templateOneFields(),
            ],
            [
                'title' => 'Kalotsav 2025-26 Student ID — Template 2',
                'audience' => 'student',
                'is_active' => false,
                'background_asset' => 'kalotsav-student-id-template-2.jpg',
                'background_filename' => 'kalotsav-student-id-template-2.jpg',
                'card_width_mm' => 89,
                'card_height_mm' => 135,
                'grid_json' => [
                    'cols' => 5,
                    'rows' => 2,
                    'first_col_center_mm' => 54.036,
                    'first_row_center_mm' => 87.96,
                    'col_pitch_mm' => 92.958,
                    'row_pitch_mm' => 138.938,
                ],
                'layout_json' => $this->templateTwoFields(),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function templateOneFields(): array
    {
        return [
            ['key' => 'photo', 'type' => 'photo', 'source' => 'photo_src', 'top' => 31.75, 'left' => 30.4, 'width' => 36.5, 'height' => 23.45],
            ['key' => 'ribbon_shape', 'type' => 'shape', 'top' => 57.6, 'left' => 12.5, 'width' => 75, 'height' => 8.2, 'radius' => 2.4, 'gradient_from' => '#FED300', 'gradient_to' => '#D40200'],
            ['key' => 'name', 'type' => 'text', 'source' => 'name', 'top' => 57.75, 'left' => 14, 'width' => 72, 'height' => 7.9, 'font_size' => 15, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#ffffff', 'line_height' => 1.05, 'wrap' => true],
            ['key' => 'badge_value', 'type' => 'text', 'source' => 'school_code', 'top' => 39.1, 'left' => 85.5, 'width' => 18, 'height' => 3.2, 'font_size' => 10, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#12345a', 'rotation' => 270],
            ['key' => 'school_name', 'type' => 'text', 'source' => 'subtitle', 'top' => 67, 'left' => 7, 'width' => 86, 'font_size' => 12, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#12345a'],
            ['key' => 'divider1', 'type' => 'divider', 'orientation' => 'vertical', 'top' => 70, 'left' => 25, 'height' => 5.6],
            ['key' => 'divider2', 'type' => 'divider', 'orientation' => 'vertical', 'top' => 70, 'left' => 50, 'height' => 5.6],
            ['key' => 'divider3', 'type' => 'divider', 'orientation' => 'vertical', 'top' => 70, 'left' => 75, 'height' => 5.6],
            ['key' => 'lbl_class', 'type' => 'static_text', 'text' => 'Class', 'top' => 70.1, 'left' => 1, 'width' => 24, 'font_size' => 10, 'font_family' => 'Arial', 'color' => '#64748b', 'align' => 'center'],
            ['key' => 'lbl_category', 'type' => 'static_text', 'text' => 'Category', 'top' => 70.1, 'left' => 25, 'width' => 25, 'font_size' => 10, 'font_family' => 'Arial', 'color' => '#64748b', 'align' => 'center'],
            ['key' => 'lbl_regno', 'type' => 'static_text', 'text' => 'Reg No', 'top' => 70.1, 'left' => 50, 'width' => 25, 'font_size' => 10, 'font_family' => 'Arial', 'color' => '#64748b', 'align' => 'center'],
            ['key' => 'lbl_gender', 'type' => 'static_text', 'text' => 'Gender', 'top' => 70.1, 'left' => 75, 'width' => 24, 'font_size' => 10, 'font_family' => 'Arial', 'color' => '#64748b', 'align' => 'center'],
            ['key' => 'class', 'type' => 'text', 'source' => 'student_class', 'top' => 72.8, 'left' => 1, 'width' => 24, 'font_size' => 11, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#12345a'],
            ['key' => 'category', 'type' => 'text', 'source' => 'category', 'top' => 72.8, 'left' => 25, 'width' => 25, 'font_size' => 11, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#12345a'],
            ['key' => 'reg_no', 'type' => 'text', 'source' => 'id_number', 'top' => 72.8, 'left' => 50, 'width' => 25, 'font_size' => 11, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#12345a'],
            ['key' => 'gender', 'type' => 'text', 'source' => 'gender', 'top' => 72.8, 'left' => 75, 'width' => 24, 'font_size' => 11, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#12345a'],
            ['key' => 'items_header_shape', 'type' => 'shape', 'top' => 77.3, 'left' => 21.5, 'width' => 57, 'height' => 4.8, 'radius' => 3.5, 'gradient_from' => '#20B6F6', 'gradient_to' => '#7C3CF0'],
            ['key' => 'items_header_label', 'type' => 'static_text', 'text' => 'PARTICIPATING ITEMS', 'top' => 78.4, 'left' => 21.5, 'width' => 57, 'font_size' => 10, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#ffffff'],
            ['key' => 'participating_items', 'type' => 'item_list', 'source' => 'participating_items', 'top' => 82.7, 'left' => 8, 'width' => 90, 'height' => 11.45, 'font_size' => 9, 'font_family' => 'Arial', 'font_weight' => 'normal', 'font_style' => 'normal', 'align' => 'left', 'color' => '#12345a', 'line_height' => 1.15, 'columns' => 2, 'max_items' => 7],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function templateTwoFields(): array
    {
        return [
            ['key' => 'photo', 'type' => 'photo', 'source' => 'photo_src', 'top' => 27.4, 'left' => 27.95, 'width' => 35, 'height' => 23.07],
            ['key' => 'badge_value', 'type' => 'text', 'source' => 'school_code', 'top' => 39.2, 'left' => 88.5, 'width' => 14, 'height' => 3.4, 'font_size' => 10, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#12345a', 'rotation' => 270],
            ['key' => 'name', 'type' => 'text', 'source' => 'name', 'top' => 56.8, 'left' => 19, 'width' => 58, 'height' => 8.7, 'font_size' => 16, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#ffffff', 'line_height' => 1.08, 'wrap' => true],
            ['key' => 'school_label', 'type' => 'static_text', 'text' => 'SCHOOL NAME', 'top' => 69.8, 'left' => 10, 'width' => 80, 'font_size' => 10, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#333333'],
            ['key' => 'school_name', 'type' => 'text', 'source' => 'subtitle', 'top' => 71.3, 'left' => 8, 'width' => 84, 'height' => 3.8, 'font_size' => 13, 'font_family' => 'Arial', 'font_weight' => 'normal', 'align' => 'center', 'color' => '#333333', 'line_height' => 1.1, 'wrap' => true],
            ['key' => 'student_info_inline', 'type' => 'text', 'source' => 'student_info_inline', 'text_format' => "CATEGORY: {category}\u{2003}\u{2003}ROLL NO: {roll_no}\u{2003}\u{2003}GENDER: {gender_upper}", 'top' => 78, 'left' => 4.5, 'width' => 91, 'height' => 3, 'font_size' => 10, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#ffffff', 'line_height' => 1, 'wrap' => false],
            ['key' => 'items_header_label', 'type' => 'static_text', 'text' => 'PARTICIPATING ITEMS', 'top' => 81.8, 'left' => 10, 'width' => 80, 'height' => 2.2, 'font_size' => 10, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#ffffff', 'line_height' => 1],
            ['key' => 'participating_items', 'type' => 'item_list', 'source' => 'participating_items', 'top' => 84.55, 'left' => 5.5, 'width' => 89, 'height' => 10.3, 'font_size' => 10, 'font_family' => 'Arial', 'font_weight' => 'normal', 'font_style' => 'normal', 'align' => 'left', 'color' => '#ffffff', 'line_height' => 1.15, 'columns' => 2, 'max_items' => 7],
        ];
    }
}
