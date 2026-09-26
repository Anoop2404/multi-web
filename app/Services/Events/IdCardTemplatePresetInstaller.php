<?php

namespace App\Services\Events;

use App\Models\IdCardTemplate;
use App\Support\TenantStorage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class IdCardTemplatePresetInstaller
{
    /** @return list<array{key: string, label: string, title: string, description: string, size_label: string}> */
    public function options(): array
    {
        return collect($this->definitions())
            ->map(fn (array $definition, string $key) => [
                'key' => $key,
                'label' => $definition['label'],
                'title' => $definition['title'],
                'description' => $definition['description'],
                'size_label' => $definition['card_width_mm'].' × '.$definition['card_height_mm'].' mm',
            ])
            ->values()
            ->all();
    }

    public function has(string $preset): bool
    {
        return array_key_exists($preset, $this->definitions());
    }

    /**
     * @return Collection<int, IdCardTemplate>
     */
    public function installAll(string $tenantId): Collection
    {
        return collect($this->definitions())
            ->map(fn (array $definition) => $this->installDefinition(
                $tenantId,
                $definition,
                (bool) $definition['is_active'],
            ))
            ->values();
    }

    public function install(string $tenantId, string $preset): IdCardTemplate
    {
        $definition = $this->definitions()[$preset] ?? null;
        if (! $definition) {
            throw new \InvalidArgumentException("Unknown ID-card template preset: {$preset}");
        }

        // UI-created presets start inactive so adding one cannot silently replace
        // the currently active event/audience template.
        return $this->installDefinition($tenantId, $definition, false);
    }

    private function installDefinition(string $tenantId, array $definition, bool $isActive): IdCardTemplate
    {
        $copyNumber = $this->nextCopyNumber($tenantId, $definition['title']);
        $title = $copyNumber === 1
            ? $definition['title']
            : $definition['title']." (Copy {$copyNumber})";
        $backgroundFilename = $this->copyFilename($definition['background_filename'], $copyNumber);
        $backgroundPath = $this->installBackground(
            $tenantId,
            $definition['background_asset'],
            $backgroundFilename,
        );

        if ($isActive) {
            $this->deactivateMatchingScope($tenantId, $definition['audience']);
        }

        return IdCardTemplate::create([
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
            'is_active' => $isActive,
        ]);
    }

    private function nextCopyNumber(string $tenantId, string $baseTitle): int
    {
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

    private function installBackground(string $tenantId, string $asset, string $filename): string
    {
        $source = database_path('seeders/assets/id-card-templates/'.$asset);
        if (! is_file($source)) {
            throw new \RuntimeException("ID-card template background is missing: {$source}");
        }

        $relativePath = "sahodaya/{$tenantId}/id-card-templates/backgrounds/{$filename}";
        TenantStorage::put($relativePath, File::get($source));

        if (! TenantStorage::exists($relativePath)) {
            throw new \RuntimeException("ID-card template background could not be installed: {$relativePath}");
        }

        return $relativePath;
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

    /** @return array<string, array<string, mixed>> */
    private function definitions(): array
    {
        return [
            'template-1' => [
                'label' => 'Template 1',
                'title' => 'Kalotsav 2025-26 Student ID (die-cut)',
                'description' => 'Classic portrait card with a bright name ribbon and four student details.',
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
            'template-2' => [
                'label' => 'Template 2',
                'title' => 'Kalotsav 2025-26 Student ID — Template 2',
                'description' => 'Purple and pink portrait card with a compact participant-information panel.',
                'audience' => 'student',
                'is_active' => false,
                'background_asset' => 'kalotsav-student-id-template-2-srgb.jpg',
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
            'template-3' => [
                'label' => 'Template 3',
                'title' => 'Kalotsav 2026-27 Student ID — Template 3',
                'description' => 'Kannur portrait card with a vertical school code and spacious numbered items.',
                'audience' => 'student',
                'is_active' => false,
                'background_asset' => 'kalotsav-student-id-template-3.png',
                'background_filename' => 'kalotsav-student-id-template-3.png',
                'card_width_mm' => 90,
                'card_height_mm' => 135,
                'grid_json' => [
                    'cols' => 5,
                    'rows' => 2,
                    'first_col_center_mm' => 54.036,
                    'first_row_center_mm' => 87.96,
                    'col_pitch_mm' => 92.958,
                    'row_pitch_mm' => 138.938,
                ],
                'layout_json' => $this->templateThreeFields(),
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
            ['key' => 'school_label', 'type' => 'static_text', 'text' => 'SCHOOL NAME', 'top' => 67.3, 'left' => 4, 'width' => 92, 'height' => 2.3, 'font_size' => 11, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#333333', 'line_height' => 1],
            ['key' => 'school_name', 'type' => 'text', 'source' => 'subtitle', 'top' => 69.8, 'left' => 4, 'width' => 92, 'height' => 5.5, 'font_size' => 10, 'font_family' => 'Arial', 'font_weight' => 'normal', 'align' => 'center', 'color' => '#333333', 'line_height' => 1.08, 'wrap' => true],
            ['key' => 'student_info_inline', 'type' => 'text', 'source' => 'student_info_inline', 'text_format' => "CATEGORY: {category}\u{2003}\u{2003}ROLL NO: {roll_no}\u{2003}\u{2003}GENDER: {gender_upper}", 'top' => 78, 'left' => 4.5, 'width' => 91, 'height' => 3, 'font_size' => 10, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#ffffff', 'line_height' => 1, 'wrap' => false],
            ['key' => 'items_header_label', 'type' => 'static_text', 'text' => 'PARTICIPATING ITEMS', 'top' => 81.8, 'left' => 10, 'width' => 80, 'height' => 2.2, 'font_size' => 10, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#ffffff', 'line_height' => 1],
            ['key' => 'participating_items', 'type' => 'item_list', 'source' => 'participating_items', 'top' => 84.55, 'left' => 5.5, 'width' => 89, 'height' => 10.3, 'font_size' => 10, 'font_family' => 'Arial', 'font_weight' => 'normal', 'font_style' => 'normal', 'align' => 'left', 'color' => '#ffffff', 'line_height' => 1.15, 'columns' => 2, 'max_items' => 7],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function templateThreeFields(): array
    {
        return [
            ['key' => 'photo', 'type' => 'photo', 'source' => 'photo_src', 'top' => 30.9, 'left' => 33.5, 'width' => 33, 'height' => 21.6],
            ['key' => 'badge_value', 'type' => 'text', 'source' => 'school_code', 'top' => 39.5, 'left' => 86.5, 'width' => 16, 'height' => 3, 'font_size' => 8, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#312e81', 'rotation' => 270],
            ['key' => 'name_pointer', 'type' => 'shape', 'top' => 63.6, 'left' => 47.6, 'width' => 4.8, 'height' => 3.2, 'rotation' => 45, 'color' => '#d51bb1'],
            ['key' => 'ribbon_shape', 'type' => 'shape', 'top' => 55.2, 'left' => 22.8, 'width' => 54.4, 'height' => 9.4, 'radius' => 2.4, 'gradient_from' => '#e2b916', 'gradient_to' => '#ca08e3'],
            ['key' => 'name', 'type' => 'text', 'source' => 'name', 'top' => 55.6, 'left' => 24.5, 'width' => 51, 'height' => 8.4, 'font_size' => 13, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#ffffff', 'line_height' => 1.05, 'wrap' => true],
            ['key' => 'school_label', 'type' => 'static_text', 'text' => 'SCHOOL NAME', 'top' => 67.4, 'left' => 10, 'width' => 80, 'height' => 2.3, 'font_size' => 12, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#111827', 'line_height' => 1],
            ['key' => 'school_name', 'type' => 'text', 'source' => 'subtitle', 'top' => 69.8, 'left' => 6, 'width' => 88, 'height' => 4.2, 'font_size' => 10, 'font_family' => 'Arial', 'font_weight' => 'normal', 'align' => 'center', 'color' => '#111827', 'line_height' => 1.05, 'wrap' => true],
            ['key' => 'student_info_divider', 'type' => 'divider', 'orientation' => 'horizontal', 'top' => 75, 'left' => 8, 'width' => 84, 'color' => '#4b5563'],
            ['key' => 'student_info_inline', 'type' => 'text', 'source' => 'student_info_inline', 'text_format' => "CATEGORY: {category}\u{2003}\u{2003}REG NO: {roll_no}\u{2003}\u{2003}GENDER: {gender_upper}", 'top' => 76.2, 'left' => 5, 'width' => 90, 'height' => 3.1, 'font_size' => 11, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#111827', 'line_height' => 1, 'wrap' => false],
            ['key' => 'items_header_label', 'type' => 'static_text', 'text' => 'PARTICIPATING ITEMS', 'top' => 81, 'left' => 10, 'width' => 80, 'height' => 2.4, 'font_size' => 11, 'font_family' => 'Arial', 'font_weight' => 'bold', 'align' => 'center', 'color' => '#111827', 'line_height' => 1],
            ['key' => 'participating_items', 'type' => 'item_list', 'source' => 'participating_items', 'top' => 84, 'left' => 7, 'width' => 86, 'height' => 10.5, 'font_size' => 10, 'font_family' => 'Arial', 'font_weight' => 'normal', 'font_style' => 'normal', 'align' => 'left', 'color' => '#111827', 'line_height' => 1.15, 'columns' => 2, 'max_items' => 7],
        ];
    }
}
