<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McqExamPaperAssignment extends Model
{
    protected $fillable = ['exam_id', 'mapping_json', 'questions_json'];
    protected $casts = ['mapping_json' => 'array', 'questions_json' => 'array'];
    protected $hidden = ['questions_json'];
}
