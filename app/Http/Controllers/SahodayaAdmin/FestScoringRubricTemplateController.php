<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestScoringRubricTemplate;
use App\Models\FestScoringRubricTemplateCriterion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD for the tenant-wide scoring-rubric template library — mirrors
 * FestEventSettingsController's FestClassCategoryScheme CRUD (same named-header +
 * ordered-child-rows shape), kept in its own controller since templates aren't tied to
 * any one event's settings the way class-category schemes ended up being.
 */
class FestScoringRubricTemplateController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        return $this->inertia('Sahodaya/ScoringRubricTemplates/Index', [
            'templates' => FestScoringRubricTemplate::forTenant($this->sahodaya->id)
                ->with('criteria')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('fest_scoring_rubric_templates', 'name')->where('tenant_id', $this->sahodaya->id),
            ],
            'description' => 'nullable|string|max:500',
        ]);

        $nextOrder = (int) FestScoringRubricTemplate::forTenant($this->sahodaya->id)->max('sort_order') + 1;

        FestScoringRubricTemplate::create(array_merge($data, [
            'tenant_id' => $this->sahodaya->id,
            'sort_order' => $nextOrder,
        ]));

        return back()->with('success', 'Rubric template created.');
    }

    public function update(Request $request, string $tenantId, FestScoringRubricTemplate $scoringRubricTemplate)
    {
        abort_if($scoringRubricTemplate->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('fest_scoring_rubric_templates', 'name')
                    ->where('tenant_id', $this->sahodaya->id)
                    ->ignore($scoringRubricTemplate->id),
            ],
            'description' => 'nullable|string|max:500',
        ]);

        $scoringRubricTemplate->update($data);

        return back()->with('success', 'Rubric template updated.');
    }

    public function destroy(string $tenantId, FestScoringRubricTemplate $scoringRubricTemplate)
    {
        abort_if($scoringRubricTemplate->tenant_id !== $this->sahodaya->id, 403);

        // Safe to delete unconditionally, unlike class-category schemes: applying a
        // template copies its criteria into the target item's own FestMarkCriterion rows
        // (see FestMarkCriteriaService::applyTemplateToItem()) rather than keeping a live
        // reference, so items that already used this template are unaffected.
        $name = $scoringRubricTemplate->name;
        $scoringRubricTemplate->delete();

        return back()->with('success', "Rubric template \"{$name}\" removed.");
    }

    public function storeCriterion(Request $request, string $tenantId, FestScoringRubricTemplate $scoringRubricTemplate)
    {
        abort_if($scoringRubricTemplate->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'label' => 'required|string|max:100',
            'max_score' => 'nullable|numeric|min:0.5',
        ]);

        $nextOrder = (int) $scoringRubricTemplate->criteria()->max('sort_order') + 1;

        $scoringRubricTemplate->criteria()->create([
            'tenant_id' => $this->sahodaya->id,
            'label' => $data['label'],
            'max_score' => $data['max_score'] ?? 10,
            'sort_order' => $nextOrder,
        ]);

        return back()->with('success', 'Column added.');
    }

    public function updateCriterion(Request $request, string $tenantId, FestScoringRubricTemplate $scoringRubricTemplate, FestScoringRubricTemplateCriterion $criterion)
    {
        abort_if($scoringRubricTemplate->tenant_id !== $this->sahodaya->id, 403);
        abort_if($criterion->template_id !== $scoringRubricTemplate->id, 404);

        $data = $request->validate([
            'label' => 'required|string|max:100',
            'max_score' => 'nullable|numeric|min:0.5',
        ]);

        $criterion->update([
            'label' => $data['label'],
            'max_score' => $data['max_score'] ?? 10,
        ]);

        return back()->with('success', 'Column updated.');
    }

    public function destroyCriterion(string $tenantId, FestScoringRubricTemplate $scoringRubricTemplate, FestScoringRubricTemplateCriterion $criterion)
    {
        abort_if($scoringRubricTemplate->tenant_id !== $this->sahodaya->id, 403);
        abort_if($criterion->template_id !== $scoringRubricTemplate->id, 404);

        $criterion->delete();

        return back()->with('success', 'Column removed.');
    }
}
