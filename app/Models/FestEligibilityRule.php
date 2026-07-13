<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FestEligibilityRule extends Model
{
    public const SCOPE_EVENT = 'event';

    public const SCOPE_AREA = 'area';

    public const SCOPE_ITEM = 'item';

    public const RULE_TYPES = [
        'audience' => 'Audience (student / teacher)',
        'gender' => 'Gender',
        'class_group' => 'Class category',
        'age_group' => 'Age group',
        'kids_band' => 'Kids band',
        'require_verified' => 'Require verified student',
        'school' => 'School allow / deny',
        'region' => 'Region allow / deny',
        'custom_ids' => 'Specific student IDs',
        'require_prior_qualification' => 'Prior qualification required',
    ];

    protected $fillable = [
        'tenant_id', 'scope_type', 'scope_id', 'rule_type', 'operator',
        'value_json', 'logic_group', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'value_json' => 'array',
        'is_active' => 'boolean',
    ];
}
