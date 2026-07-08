<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id','title','file_path','file_name','file_size','category','academic_year','display_order','is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function tenant() { return $this->belongsToCentralTenant(); }
    public function scopeActive($q) { return $q->where('is_active', true)->orderBy('display_order'); }
    public function scopeByCategory($q, string $category) { return $q->where('category', $category); }

    public function getCategoryLabelAttribute(): string
    {
        return match($this->category) {
            'booklist'      => 'Book List',
            'calendar'      => 'Academic Calendar',
            'circular'      => 'Circular',
            'question_paper'=> 'Question Paper',
            'annual_report' => 'Annual Report',
            'form'          => 'Form',
            'minutes'       => 'Minutes',
            default         => 'Other',
        };
    }
}
