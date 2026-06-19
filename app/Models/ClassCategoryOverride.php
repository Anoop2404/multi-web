<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class ClassCategoryOverride extends Model
{
    use CentralConnection;
    protected $fillable = ['sahodaya_id', 'class_category_id', 'is_hidden', 'sort_order'];

    protected $casts = ['is_hidden' => 'boolean'];

    public function sahodaya()       { return $this->belongsTo(Tenant::class, 'sahodaya_id'); }
    public function classCategory()  { return $this->belongsTo(ClassCategory::class); }
}
