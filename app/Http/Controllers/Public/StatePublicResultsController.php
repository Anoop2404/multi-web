<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\State\StateFestEvent;
use App\Services\State\StatePublicResultsProjectionService;
use Illuminate\Http\Request;

class StatePublicResultsController extends Controller
{
    public function index(Request $request, StatePublicResultsProjectionService $service)
    {
        $event = StateFestEvent::where('results_published', true)
            ->latest('updated_at')
            ->first();

        $results = $event ? $service->getPublicResults($event) : [];

        return view('public.state-results', compact('event', 'results'));
    }
}
