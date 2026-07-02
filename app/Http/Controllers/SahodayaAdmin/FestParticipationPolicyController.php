<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestParticipationPolicy;
use App\Services\Events\FestParticipationPolicyService;
use App\Support\Fest\FestParticipationPolicyPayload;
use App\Support\FestPageActivity;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;

class FestParticipationPolicyController extends SahodayaAdminController
{
    public function store(Request $request, string $tenantId, FestEvent $event, FestParticipationPolicyService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'preset_key' => 'nullable|string|max:60',
            'class_group' => 'nullable|in:lp,up,hs,hss,open',
            'max_onstage_per_school' => 'nullable|integer|min:0',
            'max_offstage_per_school' => 'nullable|integer|min:0',
            'max_group_per_school' => 'nullable|integer|min:0',
            'max_onstage_per_student' => 'nullable|integer|min:0',
            'max_offstage_per_student' => 'nullable|integer|min:0',
            'max_group_per_student' => 'nullable|integer|min:0',
            'max_total_per_student' => 'nullable|integer|min:0',
            'one_entry_per_item_per_school' => 'nullable|boolean',
            'count_submitted_registrations' => 'nullable|boolean',
            'require_fee_before_approval' => 'nullable|boolean',
        ]);

        if (! empty($data['preset_key'])) {
            $service->applyPresetToEvent($event, $data['preset_key'], $data['class_group'] ?? null);

            $audit->festEvent($event, FestPageActivity::settingsTab('participation'), 'fest.participation.preset', "Participation preset applied: {$data['preset_key']}");

            return redirect("/sahodaya-admin/{$tenantId}/events/{$event->id}/settings/participation")
                ->with('success', 'Participation policy preset applied.');
        }

        $data = FestParticipationPolicyPayload::applyDefaults($data);

        FestParticipationPolicy::updateOrCreate(
            ['event_id' => $event->id, 'class_group' => $data['class_group'] ?? null],
            array_merge($data, [
                'tenant_id' => $this->sahodaya->id,
                'scope' => 'event',
                'level_round' => $event->level_round ?? 'sahodaya',
                'is_active' => true,
            ])
        );

        $audit->festEvent($event, FestPageActivity::settingsTab('participation'), 'fest.participation.saved', 'Participation policy saved');

        return redirect("/sahodaya-admin/{$tenantId}/events/{$event->id}/settings/participation")
            ->with('success', 'Participation policy saved.');
    }
}
