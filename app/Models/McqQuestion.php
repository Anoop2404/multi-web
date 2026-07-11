<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McqQuestion extends Model
{
    protected $fillable = [
        'bank_id', 'title', 'body_text', 'document_path', 'display_order', 'created_by_user_id',
        'options_json', 'correct_option_key', 'marks', 'negative_mark',
    ];

    protected $casts = [
        'options_json'  => 'array',
        'marks'         => 'decimal:2',
        'negative_mark' => 'decimal:2',
    ];

    /** Positive marks awarded for a correct answer (default 1). */
    public function marksValue(): float
    {
        $marks = (float) ($this->marks ?? 1);

        return $marks > 0 ? $marks : 1.0;
    }

    /** Marks deducted for an incorrect (answered) attempt (default 0). */
    public function negativeMarkValue(): float
    {
        return max(0.0, (float) ($this->negative_mark ?? 0));
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(McqQuestionBank::class, 'bank_id');
    }
}
