<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Subject extends Model
{
    use CentralConnection;

    // Category values (nullable, additive — see 2026_08_10_000001_add_category_to_subjects.php):
    // 'language'  — Category I, student picks 2
    // 'science' / 'commerce' / 'humanities' — Category II stream electives, student picks 3
    //   from their stream's pool (a subject can only carry one category here even though a
    //   few, e.g. Economics, are genuinely offered under more than one stream in practice)
    // 'skill'     — Category III, fully optional additional subject
    public const CATEGORY_LANGUAGE = 'language';
    public const CATEGORY_SCIENCE = 'science';
    public const CATEGORY_COMMERCE = 'commerce';
    public const CATEGORY_HUMANITIES = 'humanities';
    public const CATEGORY_SKILL = 'skill';

    protected $fillable = ['sahodaya_id', 'code', 'label', 'category', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function sahodaya() { return $this->belongsTo(Tenant::class, 'sahodaya_id'); }

    public function isGlobal(): bool { return $this->sahodaya_id === null; }

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function scopeGlobal($q) { return $q->whereNull('sahodaya_id'); }

    public function scopeCategory($q, string $category) { return $q->where('category', $category); }

    public function scopeForSahodaya($q, string $sahodayaId)
    {
        return $q->where(function ($inner) use ($sahodayaId) {
            $inner->whereNull('sahodaya_id')->orWhere('sahodaya_id', $sahodayaId);
        });
    }
}
