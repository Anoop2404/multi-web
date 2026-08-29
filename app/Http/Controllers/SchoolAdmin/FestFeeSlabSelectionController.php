<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
use App\Services\Events\FestSchoolFeeSlabSelectionService;
use Illuminate\Http\Request;

class FestFeeSlabSelectionController extends SchoolAdminController
{
    public function store(
        Request $request,
        string $tenantId,
        FestEvent $event,
        FestSchoolFeeSlabSelectionService $selections,
    ) {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        $data = $request->validate([
            'min_count' => 'required|integer|min:0',
            'max_count' => 'nullable|integer|min:0',
        ]);

        $selection = $selections->select(
            $event,
            $this->school->id,
            (int) $data['min_count'],
            isset($data['max_count']) ? (int) $data['max_count'] : null,
            $request->user()?->id,
        );

        return back()->with('success', "Fee band selected: ₹{$selection->amount}.");
    }
}
