<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionPaperFile extends Model
{
    protected $fillable = [
        'question_paper_id', 'file_path', 'storage_disk', 'original_name',
        'mime_type', 'file_size', 'display_order',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'display_order' => 'integer',
    ];

    public function questionPaper(): BelongsTo
    {
        return $this->belongsTo(QuestionPaper::class);
    }
}
