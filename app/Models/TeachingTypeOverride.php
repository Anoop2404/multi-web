<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class TeachingTypeOverride extends Model
{
    use CentralConnection;
    protected $fillable = ['sahodaya_id', 'teaching_type_id', 'is_hidden'];

    protected $casts = ['is_hidden' => 'boolean'];

    public function sahodaya()      { return $this->belongsTo(Tenant::class, 'sahodaya_id'); }
    public function teachingType()  { return $this->belongsTo(TeachingType::class); }
}
