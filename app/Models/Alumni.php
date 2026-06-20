<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alumni extends Model
{
    protected $table = 'alumni';

    protected $fillable = ['tenant_id','name','batch_year','current_role','current_organisation','photo','message','email','is_featured','is_approved'];
    protected $casts = ['is_featured' => 'boolean', 'is_approved' => 'boolean'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function scopeApproved($q) { return $q->where('is_approved', true); }
    public function scopeFeatured($q) { return $q->where('is_featured', true); }
}
