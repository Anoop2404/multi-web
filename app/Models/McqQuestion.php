<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McqQuestion extends Model
{
    protected $fillable = [
        'bank_id', 'title', 'body_text', 'document_path', 'display_order', 'created_by_user_id',
        'options_json', 'correct_option_key',
    ];

    protected $casts = [
        'options_json' => 'array',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(McqQuestionBank::class, 'bank_id');
    }
}
