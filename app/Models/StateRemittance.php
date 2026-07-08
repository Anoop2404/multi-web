<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class StateRemittance extends Model
{
    use BelongsToCentralTenant;

    use CentralConnection;
    protected $fillable = [
        'sahodaya_id', 'title', 'description', 'amount', 'due_date', 'academic_year',
        'status', 'proof_path', 'transaction_ref', 'bank_name', 'payment_date',
        'rejection_reason', 'created_by', 'reviewed_by', 'reviewed_at', 'source_breakdown',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'due_date'    => 'date',
        'payment_date'=> 'date',
        'reviewed_at' => 'datetime',
        'source_breakdown' => 'array',
    ];

    public function sahodaya(): BelongsTo
    {
        return $this->belongsToCentralTenant('sahodaya_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
