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

    public const TIE_INCLUDE_GROUP = 'include_group';

    public const TIE_HARD_CAP = 'hard_cap';

    public const RANK_COMPETITION = 'competition';

    public const RANK_DENSE = 'dense';

    public const RANK_SEQUENTIAL = 'sequential';

    protected $fillable = [
        'sahodaya_id',
        'academic_year',
        'class',
        'scope',
        'stream_id',
        'subject_id',
        'top_n',
        'tie_mode',
        'rank_style',
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
