<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolDocumentType extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'sahodaya_id', 'code', 'name', 'is_required', 'validity_months', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_required'      => 'boolean',
        'is_active'        => 'boolean',
        'validity_months'  => 'integer',
        'sort_order'       => 'integer',
    ];

    public function sahodaya()
    {
        return $this->belongsToCentralTenant('sahodaya_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SchoolDocument::class, 'document_type_id');
    }
}
