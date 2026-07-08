<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class McqGradeMaster extends Model
{
    protected $fillable = ['tenant_id', 'title', 'is_default', 'is_active'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function bands(): HasMany
    {
        return $this->hasMany(McqGradeBand::class, 'grade_master_id')->orderBy('display_order');
    }
}
