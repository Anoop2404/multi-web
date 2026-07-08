<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class AgeCategory extends Model
{
    use CentralConnection;

    protected $fillable = [
        'sahodaya_id', 'code', 'label', 'max_age', 'cutoff_date', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_age'   => 'integer',
    ];

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

    /** Age in completed years on the cutoff date for a given birth date. */
    public function ageOnCutoff(\DateTimeInterface $dob, ?int $year = null): int
    {
        $year = $year ?? (int) date('Y');
        [$month, $day] = array_map('intval', explode('-', $this->cutoff_date ?: '12-31'));
        $cutoff = new \DateTimeImmutable(sprintf('%d-%02d-%02d', $year, $month, $day));

        return $cutoff->diff(new \DateTimeImmutable($dob->format('Y-m-d')))->y;
    }

    public function isEligible(\DateTimeInterface $dob, ?int $year = null): bool
    {
        return $this->ageOnCutoff($dob, $year) <= $this->max_age;
    }
}
