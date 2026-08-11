<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ExternalSchool;
use App\Models\FestStateProgramItem;
use App\Models\State\StateQualifierEntry;
use App\Services\External\ExternalPortalOtpService;
use App\Services\State\ExternalIntakeService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Code-gated school portal, now with an email+OTP checkpoint on top of the access code (P-02,
 * see ExternalPortalOtpService). A school added before contact_email was captured falls back
 * to access-code-only rather than being locked out.
 */
class ExternalSchoolPortalController extends Controller
{
    public function show(string $code)
    {
        $school = $this->resolve($code);

        return view('external.school-portal', [
            'school'  => $school->load('sahodaya.program'),
            'items'   => FestStateProgramItem::where('state_program_id', $school->sahodaya->state_program_id)
                ->orderBy('display_order')
                ->get(['id', 'item_code', 'title', 'class_group']),
            'entries' => StateQualifierEntry::where('school_id', $school->id)
                ->orderBy('item_code')
                ->get(),
        ]);
    }

    public function store(Request $request, string $code, ExternalIntakeService $service)
    {
        $school = $this->resolve($code);

        $data = $request->validate([
            'item_code'    => 'required|string|max:20',
            'item_name'    => 'nullable|string|max:255',
            'student_name' => 'required|string|max:255',
            'class_name'   => 'nullable|string|max:40',
            'position'     => 'nullable|integer|min:1|max:3',
            'grade'        => 'nullable|string|max:8',
        ]);

        // Item selection must come from the state catalog dropdown, not free text — keeps
        // item_code matching clean downstream (§2.2's "item catalog mismatch" open question).
        $item = FestStateProgramItem::where('state_program_id', $school->sahodaya->state_program_id)
            ->where('item_code', $data['item_code'])
            ->first();

        abort_unless($item, 422, 'Select an item from the list.');

        $service->addEntry($school, $data + [
            'item_id'       => $item->id,
            'item_name'     => $item->title,
            'qualify_count' => $item->qualify_count,
        ]);

        return back()->with('success', "Added {$data['student_name']} for {$item->title}.");
    }

    public function destroy(string $code, StateQualifierEntry $entry, ExternalIntakeService $service)
    {
        $school = $this->resolve($code);

        $service->removeEntry($school, $entry);

        return back()->with('success', 'Entry removed.');
    }

    public function showVerify(string $code)
    {
        $school = $this->find($code);

        if (! $school->requiresOtp() || app(ExternalPortalOtpService::class)->isSessionVerified($school)) {
            return redirect()->route('state.external.school.show', $school->access_code);
        }

        return view('external.verify-otp', [
            'portalType'  => 'school',
            'name'        => $school->name,
            'email'       => $school->contact_email,
            'code'        => $school->access_code,
            'sendRoute'   => route('state.external.school.verify.send', $school->access_code),
            'checkRoute'  => route('state.external.school.verify.check', $school->access_code),
            'hasPending'  => filled($school->otp_code_hash) && $school->otp_expires_at?->isFuture(),
        ]);
    }

    public function sendOtp(string $code, ExternalPortalOtpService $otp)
    {
        $school = $this->find($code);
        $otp->issue($school);

        return back()->with('success', "Code sent to {$this->maskEmail($school->contact_email)}.");
    }

    public function checkOtp(Request $request, string $code, ExternalPortalOtpService $otp)
    {
        $school = $this->find($code);

        $data = $request->validate(['otp' => 'required|string|max:6']);

        if (! $otp->verify($school, $data['otp'])) {
            return back()->withErrors(['otp' => 'That code is incorrect or has expired.']);
        }

        $otp->markSessionVerified($school);

        return redirect()->route('state.external.school.show', $school->access_code);
    }

    /** Lookup + active checks only — no OTP gate. Used by the verify screen itself. */
    private function find(string $code): ExternalSchool
    {
        $school = ExternalSchool::where('access_code', strtoupper($code))->first();

        abort_if(! $school, 404, 'Access code not recognized.');
        abort_unless($school->isActive(), 403, 'This access code has been disabled. Contact your Sahodaya coordinator.');
        abort_unless($school->sahodaya?->isActive(), 403, 'Your Sahodaya\'s access has been disabled. Contact the State Kalolsavam office.');

        return $school;
    }

    /** Lookup + active checks + OTP gate. Used by every real portal action. */
    private function resolve(string $code): ExternalSchool
    {
        $school = $this->find($code);

        if ($school->requiresOtp() && ! app(ExternalPortalOtpService::class)->isSessionVerified($school)) {
            throw new HttpResponseException(
                redirect()->route('state.external.school.verify.show', $school->access_code)
            );
        }

        return $school;
    }

    private function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return 'your registered email';
        }

        [$local, $domain] = explode('@', $email, 2);

        return substr($local, 0, 2).str_repeat('*', max(strlen($local) - 2, 1)).'@'.$domain;
    }
}
