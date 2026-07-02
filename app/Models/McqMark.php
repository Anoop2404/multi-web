<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McqMark extends Model
{
    protected $fillable = ['registration_id', 'correct_count', 'wrong_count', 'unanswered_count', 'score', 'percentage', 'grade', 'rank', 'answers_json', 'locked_by', 'locked_at'];

    protected $casts = [
        'score' => 'decimal:2',
        'percentage' => 'decimal:2',
        'answers_json' => 'array',
        'locked_at' => 'datetime',
    ];

    public function registration()
    {
        return $this->belongsTo(McqRegistration::class, 'registration_id');
    }
}
