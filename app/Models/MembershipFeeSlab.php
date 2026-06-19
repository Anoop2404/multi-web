<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipFeeSlab extends Model
{
    protected $fillable = ['sahodaya_id', 'academic_year', 'min_students', 'max_students', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function sahodaya() { return $this->belongsTo(Tenant::class, 'sahodaya_id'); }
}
