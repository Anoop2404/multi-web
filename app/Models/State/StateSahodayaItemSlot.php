<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** A slot count for one Sahodaya on one item, overriding the item's own figure. */
class StateSahodayaItemSlot extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_sahodaya_item_slots';

    protected $fillable = [
        'state_program_id', 'state_id', 'item_id', 'sahodaya_id', 'slots', 'reason', 'set_by_user_id',
    ];

    protected function casts(): array
    {
        return ['slots' => 'integer'];
    }
}
