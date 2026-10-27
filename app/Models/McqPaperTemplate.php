<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McqPaperTemplate extends Model
{
    protected $hidden = ['questions_json'];
    protected $fillable = ['tenant_id', 'title', 'version', 'definition_json', 'questions_json', 'total_marks', 'is_active'];
    protected $casts = ['definition_json' => 'array', 'questions_json' => 'array', 'total_marks' => 'decimal:2', 'is_active' => 'boolean'];
}
