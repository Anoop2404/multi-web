<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class MasterClass extends Model
{
    use CentralConnection;

    protected $fillable = [
        'sahodaya_id', 'class_category_id', 'name', 'display_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function classCategory() { return $this->belongsTo(ClassCategory::class); }
    public function sahodaya()       { return $this->belongsTo(Tenant::class, 'sahodaya_id'); }

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function scopeForSahodaya($q, string $sahodayaId)
    {
        return $q->where('sahodaya_id', $sahodayaId);
    }

    public function isTemplate(): bool
    {
        return $this->sahodaya_id === null;
    }
}
