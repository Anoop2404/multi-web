<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ExternalSahodaya;
use App\Models\FestStateProgramItem;
use App\Models\State\StateQualifierEntry;
use App\Models\StateRemittance;
use App\Services\State\ExternalIntakeService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Code-gated coordinator portal for an outside Sahodaya — the access code is the credential,
 * same shape as the manual's own "Sahodaya heads get a password for state registration"
 * process. No OTP/named-account layer (deliberately kept access-code-only).
 */
class ExternalSahodayaPortalController extends Controller
{
    public function show(string $code, ExternalIntakeService $service)
    {
        $sahodaya = $this->resolve($code);

        // Promoted Sahodayas run on the platform now. The access code keeps working as a redirect
        // because it has already been printed in circulars, but it lands on a "moved" page instead
        // of the roster — see movedResponse().
        if ($sahodaya->isPromoted()) {
            return $this->movedResponse($sahodaya);
        }

        return view('external.sahodaya-portal', [
            'sahodaya'  => $sahodaya->load('program'),
            'schools'   => $sahodaya->schools()->orderBy('name')->get(),
            'entries'   => $service->entriesForReview($sahodaya),
            'unassigned'=> $service->unassignedRoster($sahodaya),
            'items'     => FestStateProgramItem::where('state_program_id', $sahodaya->state_program_id)
                ->orderBy('display_order')
                ->get(['id', 'item_code', 'title', 'class_group']),
            'fee'       => StateRemittance::where('sahodaya_id', "external:{$sahodaya->id}")->first(),
        ]);
    }

    public function storeSchool(Request $request, string $code, ExternalIntakeService $service)
    {
        $sahodaya = $this->resolveForWrite($code);

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
        $sahodaya = $this->resolveForWrite($code);

        $service->submit($sahodaya);

        return back()->with('success', 'Submitted to State. Your entries are now with the State Kalolsavam office for review.');
    }

    /** Bulk winner-list upload — one spreadsheet for every school under this Sahodaya at once. */
    public function importWinners(Request $request, string $code, ExternalIntakeService $service)
    {
        $sahodaya = $this->resolveForWrite($code);

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        $result = $service->importWinnersFromSpreadsheet($sahodaya, $request->file('file')->getRealPath());

        $message = "Imported {$result['imported']} student(s).";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} row(s) skipped — see details below.";
        }

        return back()->with($result['skipped'] > 0 ? 'warning' : 'success', $message)
            ->with('importErrors', $result['errors']);
    }

    /**
     * The coordinator registers one uploaded roster student to a state-level item — the
     * separate step after importWinners(), mirroring the current school-level
     * "Student Registry, then register to items" flow.
     */
    public function registerItem(Request $request, string $code, ExternalIntakeService $service)
    {
        $sahodaya = $this->resolveForWrite($code);

        $data = $request->validate([
            'entry_id'  => 'required|integer',
            'item_code' => 'required|string|max:20',
            'position'  => 'nullable|integer|min:1|max:3',
            'grade'     => 'nullable|string|max:8',
        ]);

        $entry = StateQualifierEntry::findOrFail($data['entry_id']);
        $service->registerToItem($sahodaya, $entry, $data['item_code'], $data['position'] ?? null, $data['grade'] ?? null);

        return back()->with('success', "Registered {$entry->student_name} for the selected item.");
    }

    /** Custom-amount registration fee + one proof upload — no calculated fee schedule. */
    public function storeFee(Request $request, string $code, ExternalIntakeService $service)
    {
        $sahodaya = $this->resolveForWrite($code);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'proof'  => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $service->submitFee($sahodaya, (float) $data['amount'], $request->file('proof'));

        return back()->with('success', 'Payment proof submitted for state verification.');
    }

    /** Lookup + active check. Used by every portal action — access code only, no OTP gate. */
    private function resolve(string $code): ExternalSahodaya
    {
        $sahodaya = ExternalSahodaya::where('access_code', strtoupper($code))->first();

        abort_if(! $sahodaya, 404, 'Access code not recognized.');
        abort_unless($sahodaya->isActive(), 403, 'This access code has been disabled. Contact the State Kalolsavam office.');

        return $sahodaya;
    }

    /**
     * Same lookup, but refuses once the Sahodaya has been promoted to a tenant of its own
     * (docs/STATE_ADMIN_TENANT_PROMOTION_AND_UAT_PLAN_2026_09_23.md §5.3). Without this the roster
     * would exist in two places at once — here and in the Sahodaya's own database — with no way to
     * reconcile which one the State should believe.
     */
    private function resolveForWrite(string $code): ExternalSahodaya
    {
        $sahodaya = $this->resolve($code);

        if ($sahodaya->isPromoted()) {
            // Not abort(403): the app's error page renders a generic "You don't have permission",
            // which tells a coordinator nothing about where their roster went. Send them to the
            // moved page instead — the write is still refused, but the refusal explains itself and
            // carries the new portal address.
            throw new HttpResponseException(
                redirect()->route('state.external.sahodaya.show', ['code' => $code])
            );
        }

        return $sahodaya;
    }

    private function movedResponse(ExternalSahodaya $sahodaya)
    {
        return response()->view('external.sahodaya-moved', [
            'sahodaya' => $sahodaya,
            'url'      => $this->tenantUrl($sahodaya),
        ], 200);
    }

    private function tenantUrl(ExternalSahodaya $sahodaya): ?string
    {
        return $sahodaya->tenant ? \App\Support\TenantDomainSync::publicUrl($sahodaya->tenant) : null;
    }
}
