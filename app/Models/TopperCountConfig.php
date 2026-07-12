<?php

namespace App\Models;

use App\Models\Concerns\ScopesBySahodaya;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopperCountConfig extends Model
{
    use ScopesBySahodaya;
    public const SCOPE_OVERALL = 'overall';

    public const SCOPE_STREAM = 'stream';

    public const SCOPE_SUBJECT = 'subject';

    protected $fillable = [
        'sahodaya_id',
        'class',
        'scope',
        'stream_id',
        'subject_id',
        'top_n',
    ];

    protected $casts = [
        'class' => 'integer',
        'top_n' => 'integer',
    ];

    public function stream(): BelongsTo
    {
        return $this->belongsTo(ExamStream::class, 'stream_id');
    }
}
