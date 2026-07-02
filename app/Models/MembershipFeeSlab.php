<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipFeeSlab extends Model
{
    protected $fillable = ['sahodaya_id', 'academic_year', 'academic_year_id', 'min_students', 'max_students', 'amount', 'due_date', 'late_fee_amount'];

    protected $casts = ['amount' => 'decimal:2', 'due_date' => 'date', 'late_fee_amount' => 'decimal:2'];

    public function sahodaya() { return $this->belongsTo(Tenant::class, 'sahodaya_id'); }
    public function academicYearRecord() { return $this->belongsTo(AcademicYearRecord::class, 'academic_year_id'); }
}
