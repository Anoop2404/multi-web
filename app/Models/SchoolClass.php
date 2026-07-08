<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    use BelongsToCentralTenant;

    protected $table = 'school_classes';

    protected $fillable = ['tenant_id', 'class_category_id', 'name', 'display_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function tenant()         { return $this->belongsToCentralTenant(); }
    public function classCategory()  { return $this->belongsTo(ClassCategory::class); }
    public function students()       { return $this->hasMany(Student::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
