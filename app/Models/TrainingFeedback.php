<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingFeedback extends Model
{
    protected $table = 'training_feedback';

    protected $fillable = [
        'program_id',
        'registration_id',
        'teacher_id',
        'rating',
        'comments',
        'content_rating',
        'trainer_rating',
        'venue_rating',
        'status',
        'reviewed_at',
        'reviewed_by_user_id',
    ];

    protected $casts = [
        'rating' => 'integer',
        'content_rating' => 'integer',
        'trainer_rating' => 'integer',
        'venue_rating' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'program_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TrainingRegistration::class, 'registration_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
