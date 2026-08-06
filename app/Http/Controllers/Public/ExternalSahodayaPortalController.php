<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ExternalSahodaya;
use App\Services\State\ExternalIntakeService;
use Illuminate\Http\Request;

/**
 * Code-gated coordinator portal for an outside Sahodaya — no login, the access code IS the
 * credential (same shape as the manual's own "Sahodaya heads get a password" process).
 * See docs/STATE_LEVEL_KALOTSAV_ROLLOUT_PLAN.md §2.1.
 */
class ExternalSahodayaPortalController extends Controller
{
    public function show(string $code, ExternalIntakeService $service)
    {
        $sahodaya = $this->resolve($code);

        return view('external.sahodaya-portal', [
            'sahodaya' => $sahodaya->load('program'),
            'schools'  => $sahodaya->schools()->orderBy('name')->get(),
            'entries'  => $service->entriesForReview($sahodaya),
        ]);
    }

    public function storeSchool(Request $request, string $code, ExternalIntakeService $service)
    {
        $sahodaya = $this->resolve($code);

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'contact_name'  => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:40',
        ]);

        $school = $service->addSchool($sahodaya, $data);

        return back()->with(
            'success',
            "Added \"{$school->name}\". Give them access code {$school->access_code} to enter their own students."
        );
    }

    public function submit(string $code, ExternalIntakeService $service)
    {
        $sahodaya = $this->resolve($code);

        $service->submit($sahodaya);

        return back()->with('success', 'Submitted to State. Your entries are now with the State Kalolsavam office for review.');
    }

    private function resolve(string $code): ExternalSahodaya
    {
        $sahodaya = ExternalSahodaya::where('access_code', strtoupper($code))->first();

        abort_if(! $sahodaya, 404, 'Access code not recognized.');
        abort_unless($sahodaya->isActive(), 403, 'This access code has been disabled. Contact the State Kalolsavam office.');

        return $sahodaya;
    }
}
