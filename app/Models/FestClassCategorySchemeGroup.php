<?php

namespace App\Models;

use App\Support\Concerns\HasClassesSuffix;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One category (e.g. "Junior", "Category I") within a named FestClassCategoryScheme,
 * carrying the class numbers (1-12) that belong to it.
 */
class FestClassCategorySchemeGroup extends Model
{
    use HasClassesSuffix;

    protected $fillable = ['tenant_id', 'scheme_id', 'key', 'label', 'description', 'classes', 'sort_order'];

    protected $casts = [
        'classes' => 'array',
    ];

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(FestClassCategoryScheme::class, 'scheme_id');
    }
}
