<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named, Sahodaya-wide reusable mark-entry rubric (e.g. "Standard On-Stage Solo") that
 * can be applied to any item in any event owned by the tenant — see
 * FestMarkCriteriaService::applyTemplateToItem(). Mirrors FestClassCategoryScheme's shape.
 */
class FestScoringRubricTemplate extends Model
{
    protected $fillable = ['tenant_id', 'name', 'description', 'sort_order'];

    public function criteria(): HasMany
    {
        return $this->hasMany(FestScoringRubricTemplateCriterion::class, 'template_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('fest_scoring_rubric_templates.tenant_id', $tenantId);
    }
}
