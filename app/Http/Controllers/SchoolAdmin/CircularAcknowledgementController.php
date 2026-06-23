<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\Circular;
use App\Models\CircularAcknowledgement;
use Illuminate\Http\Request;

class CircularAcknowledgementController extends SchoolAdminController
{
    public function index()
    {
        $sahodayaId = $this->school->parent_id;

        $circulars = Circular::where('tenant_id', $sahodayaId)
            ->orderByDesc('issued_date')
            ->get()
            ->map(function (Circular $c) {
                $c->setAttribute('acknowledged', CircularAcknowledgement::where('circular_id', $c->id)
                    ->where('user_id', auth()->id())
                    ->exists());

                return $c;
            });

        return $this->inertia('School/Circulars/Index', compact('circulars'));
    }

    public function acknowledge(string $tenantId, Circular $circular)
    {
        abort_if($circular->tenant_id !== $this->school->parent_id, 403);

        CircularAcknowledgement::firstOrCreate(
            ['circular_id' => $circular->id, 'user_id' => auth()->id()],
            ['school_id' => $this->school->id, 'acknowledged_at' => now()]
        );

        return back()->with('success', 'Circular acknowledged.');
    }
}
