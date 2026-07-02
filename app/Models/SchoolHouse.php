<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolHouse extends Model
{
    protected $fillable = ['tenant_id', 'name', 'color', 'motto', 'sort_order'];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'school_house_id');
    }

    public function scopeForSchool($q, string $schoolId)
    {
        return $q->where('tenant_id', $schoolId);
    }
}
