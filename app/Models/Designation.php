<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Designation extends Model
{
    use CentralConnection;

    protected $fillable = ['sahodaya_id', 'code', 'label', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function sahodaya() { return $this->belongsTo(Tenant::class, 'sahodaya_id'); }

    public function isGlobal(): bool { return $this->sahodaya_id === null; }

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function scopeGlobal($q) { return $q->whereNull('sahodaya_id'); }

    public function scopeForSahodaya($q, string $sahodayaId)
    {
        return $q->where(function ($inner) use ($sahodayaId) {
            $inner->whereNull('sahodaya_id')->orWhere('sahodaya_id', $sahodayaId);
        });
    }
}
