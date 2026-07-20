<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdCardTemplate extends Model
{
    protected $fillable = [
        'tenant_id', 'event_id', 'item_id', 'audience', 'title', 'background_path',
        'card_width_mm', 'card_height_mm', 'cards_per_page', 'layout_json', 'is_active',
    ];

    protected $casts = [
        'layout_json'    => 'array',
        'is_active'      => 'boolean',
        'card_width_mm'  => 'integer',
        'card_height_mm' => 'integer',
        'cards_per_page' => 'integer',
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
            'schedule'         => 'Schedule line',
            'footer'           => 'Footer text',
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
