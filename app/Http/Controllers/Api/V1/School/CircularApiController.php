<?php

namespace App\Http\Controllers\Api\V1\School;

use App\Models\Circular;
use App\Models\CircularAcknowledgement;
use Illuminate\Http\Request;

class CircularApiController extends SchoolApiController
{
    public function index()
    {
        $circulars = Circular::where('tenant_id', $this->school->parent_id)
            ->orderByDesc('issued_date')
            ->get()
            ->map(function (Circular $c) {
                $c->setAttribute('acknowledged', CircularAcknowledgement::where('circular_id', $c->id)
                    ->where('user_id', auth()->id())
                    ->exists());

                return $c;
            });

        return response()->json(['data' => $circulars]);
    }

    public function acknowledge(Circular $circular)
    {
        abort_if($circular->tenant_id !== $this->school->parent_id, 403);

        CircularAcknowledgement::firstOrCreate(
            ['circular_id' => $circular->id, 'user_id' => auth()->id()],
            ['school_id' => $this->school->id, 'acknowledged_at' => now()]
        );

        return response()->json(['ok' => true]);
    }
}
