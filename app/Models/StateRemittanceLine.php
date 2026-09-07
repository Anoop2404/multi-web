<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class StateRemittanceLine extends Model
{
    use CentralConnection;

    protected $fillable = [
        'state_remittance_id', 'line_type', 'label', 'state_program_item_id',
        'item_code', 'quantity', 'unit_amount', 'amount', 'meta',
    ];

    protected $casts = [
        'unit_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function remittance(): BelongsTo
    {
        return $this->belongsTo(StateRemittance::class, 'state_remittance_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FestStateProgramItem::class, 'state_program_item_id');
    }
}
