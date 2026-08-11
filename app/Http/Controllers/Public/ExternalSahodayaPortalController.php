<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ExternalSahodaya;
use App\Services\External\ExternalPortalOtpService;
use App\Services\State\ExternalIntakeService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Code-gated coordinator portal for an outside Sahodaya, now with an email+OTP checkpoint on
 * top of the access code (P-02, see ExternalPortalOtpService). A record with no contact_email
 * on file falls back to access-code-only rather than being locked out.
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
            'contact_email' => 'nullable|email|max:255',
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

    public function showVerify(string $code)
    {
        $sahodaya = $this->find($code);

        if (! $sahodaya->requiresOtp() || app(ExternalPortalOtpService::class)->isSessionVerified($sahodaya)) {
            return redirect()->route('state.external.sahodaya.show', $sahodaya->access_code);
        }

        return view('external.verify-otp', [
            'portalType'  => 'sahodaya',
            'name'        => $sahodaya->name,
            'email'       => $sahodaya->contact_email,
            'code'        => $sahodaya->access_code,
            'sendRoute'   => route('state.external.sahodaya.verify.send', $sahodaya->access_code),
            'checkRoute'  => route('state.external.sahodaya.verify.check', $sahodaya->access_code),
            'hasPending'  => filled($sahodaya->otp_code_hash) && $sahodaya->otp_expires_at?->isFuture(),
        ]);
    }

    public function sendOtp(string $code, ExternalPortalOtpService $otp)
    {
        $sahodaya = $this->find($code);
        $otp->issue($sahodaya);

        return back()->with('success', "Code sent to {$this->maskEmail($sahodaya->contact_email)}.");
    }

    public function checkOtp(Request $request, string $code, ExternalPortalOtpService $otp)
    {
        $sahodaya = $this->find($code);

        $data = $request->validate(['otp' => 'required|string|max:6']);

        if (! $otp->verify($sahodaya, $data['otp'])) {
            return back()->withErrors(['otp' => 'That code is incorrect or has expired.']);
        }

        $otp->markSessionVerified($sahodaya);

        return redirect()->route('state.external.sahodaya.show', $sahodaya->access_code);
    }

    /** Lookup + active check only — no OTP gate. Used by the verify screen itself. */
    private function find(string $code): ExternalSahodaya
    {
        $sahodaya = ExternalSahodaya::where('access_code', strtoupper($code))->first();

        abort_if(! $sahodaya, 404, 'Access code not recognized.');
        abort_unless($sahodaya->isActive(), 403, 'This access code has been disabled. Contact the State Kalolsavam office.');

        return $sahodaya;
    }

    /** Lookup + active check + OTP gate. Used by every real portal action. */
    private function resolve(string $code): ExternalSahodaya
    {
        $sahodaya = $this->find($code);

        if ($sahodaya->requiresOtp() && ! app(ExternalPortalOtpService::class)->isSessionVerified($sahodaya)) {
            throw new HttpResponseException(
                redirect()->route('state.external.sahodaya.verify.show', $sahodaya->access_code)
            );
        }

        return $sahodaya;
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
