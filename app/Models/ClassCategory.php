<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class ClassCategory extends Model
{
    use CentralConnection;
    protected $fillable = [
        'sahodaya_id', 'code', 'label', 'min_class', 'max_class', 'is_active', 'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function sahodaya()   { return $this->belongsTo(Tenant::class, 'sahodaya_id'); }
    public function schoolClasses() { return $this->hasMany(SchoolClass::class); }
    public function isGlobal(): bool { return $this->sahodaya_id === null; }

    public function scopeActive($q)  { return $q->where('is_active', true); }
    public function scopeGlobal($q)   { return $q->whereNull('sahodaya_id'); }
    public function scopeForSahodaya($q, string $sahodayaId) {
        return $q->where('sahodaya_id', $sahodayaId);
    }
}
