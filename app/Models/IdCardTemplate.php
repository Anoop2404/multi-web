<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdCardTemplate extends Model
{
    protected $fillable = [
        'tenant_id', 'event_id', 'item_id', 'audience', 'title', 'background_path',
        'card_width_mm', 'card_height_mm', 'cards_per_page', 'page_width_mm', 'page_height_mm',
        'grid_json', 'layout_json', 'is_active',
    ];

    protected $casts = [
        'layout_json'    => 'array',
        'grid_json'      => 'array',
        'is_active'      => 'boolean',
        'card_width_mm'  => 'integer',
        'card_height_mm' => 'integer',
        'cards_per_page' => 'integer',
        'page_width_mm'  => 'float',
        'page_height_mm' => 'float',
    ];

    public function event()
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function item()
    {
        return $this->belongsTo(FestEventItem::class, 'item_id');
    }

    /** Data keys a field can be bound to, pulled straight from FestIdCardService card arrays. */
    public static function dataSourceOptions(): array
    {
        return [
            'name'             => 'Name',
            'subtitle'         => 'School / subtitle',
            'detail'           => 'Item / detail',
            'item_label'       => 'Item label',
            'role_label'       => 'Role (STUDENT/TEACHER/...)',
            'id_number'        => 'ID number',
            'secondary_value'  => 'Secondary value (chest no. etc)',
            'chest_number'     => 'Chest number',
            'category'         => 'Category (class/age group)',
            'gender'           => 'Gender',
            'gender_upper'     => 'Gender (uppercase)',
            'school_code'      => 'School code (Sahodaya prefix + school no.)',
            'student_reg_no'   => "Student's registration no. (e.g. STU/27/10495)",
            'roll_no'          => 'Roll No. (e.g. 10495 / STU/27/10495)',
            'student_seq_id'   => 'Student sequence no. (e.g. 10495)',
            'student_id'       => 'Student ID (e.g. STU/27/10495)',
            'student_class'    => 'Class',
            'student_info_inline' => 'Category, roll no. and gender (combined)',
            'schedule'         => 'Schedule line',
            'footer'           => 'Footer text',
            'participating_items' => 'Participating items (automatic numbered list)',
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function defaultFields(): array
    {
        return [
            [
                'key' => 'photo', 'type' => 'photo', 'source' => 'photo_src',
                'top' => 8, 'left' => 4, 'width' => 22, 'height' => 26,
            ],
            [
                'key' => 'qr', 'type' => 'qr', 'source' => 'qr_src',
                'top' => 4, 'left' => 82, 'width' => 14, 'height' => 14,
            ],
            [
                'key' => 'name', 'type' => 'text', 'source' => 'name',
                'top' => 10, 'left' => 30, 'width' => 65, 'font_size' => 13, 'font_weight' => 'bold', 'font_family' => 'Arial',
            ],
            [
                'key' => 'subtitle', 'type' => 'text', 'source' => 'subtitle',
                'top' => 22, 'left' => 30, 'width' => 65, 'font_size' => 9, 'font_family' => 'Arial',
            ],
            [
                'key' => 'detail', 'type' => 'text', 'source' => 'detail',
                'top' => 30, 'left' => 30, 'width' => 65, 'font_size' => 8, 'font_family' => 'Arial',
            ],
            [
                'key' => 'id_number', 'type' => 'text', 'source' => 'id_number',
                'top' => 80, 'left' => 4, 'width' => 45, 'font_size' => 10, 'font_weight' => 'bold', 'font_family' => 'DejaVu Sans Mono',
            ],
        ];
    }

    public function fields(): array
    {
        $fields = $this->layout_json;

        return is_array($fields) && $fields !== [] ? $fields : self::defaultFields();
    }

    /**
     * Exact physical grid for a die-cut print sheet — every card placed at a precise
     * center point (cols/rows × pitch, from an anchor center) instead of the plain
     * 2-per-row auto-flow table custom-sheet.blade.php otherwise uses. Column/row
     * pitch (center-to-center distance) can be smaller than the card's own width/
     * height on purpose — a die-cut sheet is printed with bleed, so adjacent cards'
     * bleed areas are expected to slightly overlap; that's normal, not a layout bug.
     * Returns null (→ fall back to the auto-flow table) unless every required key is
     * present and cols/rows are positive.
     *
     * @return ?array{cols: int, rows: int, first_col_center_mm: float, first_row_center_mm: float, col_pitch_mm: float, row_pitch_mm: float}
     */
    public function gridLayout(): ?array
    {
        $g = $this->grid_json;
        if (! is_array($g)) {
            return null;
        }

        $cols = isset($g['cols']) && is_numeric($g['cols']) ? (int) $g['cols'] : 0;
        $rows = isset($g['rows']) && is_numeric($g['rows']) ? (int) $g['rows'] : 0;
        if ($cols < 1 || $rows < 1) {
            return null;
        }

        $pageW = (float) ($this->page_width_mm ?: 297);
        $pageH = (float) ($this->page_height_mm ?: 210);

        $colPitch = isset($g['col_pitch_mm']) && is_numeric($g['col_pitch_mm'])
            ? (float) $g['col_pitch_mm']
            : ($pageW / $cols);

        $rowPitch = isset($g['row_pitch_mm']) && is_numeric($g['row_pitch_mm'])
            ? (float) $g['row_pitch_mm']
            : ($pageH / $rows);

        $firstColCenter = isset($g['first_col_center_mm']) && is_numeric($g['first_col_center_mm'])
            ? (float) $g['first_col_center_mm']
            : ($colPitch / 2);

        $firstRowCenter = isset($g['first_row_center_mm']) && is_numeric($g['first_row_center_mm'])
            ? (float) $g['first_row_center_mm']
            : ($rowPitch / 2);

        return [
            'cols'                 => $cols,
            'rows'                 => $rows,
            'first_col_center_mm'  => $firstColCenter,
            'first_row_center_mm'  => $firstRowCenter,
            'col_pitch_mm'         => $colPitch,
            'row_pitch_mm'         => $rowPitch,
        ];
    }

    /**
     * Resolve the most specific active ID card template for an event/item/audience.
     * Cascade: item+audience -> item (any audience) -> event+audience -> event (any
     * audience) -> tenant-wide default (+audience) -> tenant-wide default (any audience).
     */
    public static function resolveFor(FestEvent $event, ?int $itemId, string $audience): ?self
    {
        $tenantId = $event->tenant_id;

        $attempts = [];
        if ($itemId) {
            $attempts[] = ['event_id' => $event->id, 'item_id' => $itemId, 'audience' => $audience];
            $attempts[] = ['event_id' => $event->id, 'item_id' => $itemId, 'audience' => null];
        }
        $attempts[] = ['event_id' => $event->id, 'item_id' => null, 'audience' => $audience];
        $attempts[] = ['event_id' => $event->id, 'item_id' => null, 'audience' => null];
        $attempts[] = ['event_id' => null, 'item_id' => null, 'audience' => $audience];
        $attempts[] = ['event_id' => null, 'item_id' => null, 'audience' => null];

        foreach ($attempts as $attempt) {
            $query = self::where('tenant_id', $tenantId)->where('is_active', true);

            $query = $attempt['event_id'] ? $query->where('event_id', $attempt['event_id']) : $query->whereNull('event_id');
            $query = $attempt['item_id'] ? $query->where('item_id', $attempt['item_id']) : $query->whereNull('item_id');
            $query = $attempt['audience'] ? $query->where('audience', $attempt['audience']) : $query->whereNull('audience');

            $template = $query->latest()->first();
            if ($template) {
                return $template;
            }
        }

        return null;
    }
}
