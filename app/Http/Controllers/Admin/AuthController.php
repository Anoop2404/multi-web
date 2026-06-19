<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\SahodayaMailer;
use App\Support\SahodayaHomepageContent;
use App\Support\TenantBranding;
use App\Support\TenantDomainSync;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(): Response
    {
        if (TenantDomainSync::isCentralHost(request()->getHost())) {
            return inertia('Auth/SuperadminLogin', [
                'appName' => config('app.name'),
                'sessionExpired' => request()->query('session') === 'expired',
            ]);
        }

        $tenant = TenantBranding::resolveTenant();
        $branding = ($tenant && $tenant->type === 'sahodaya')
            ? SahodayaHomepageContent::get($tenant)
            : [];

        return inertia('Auth/Login', [
            'logoUrl'    => TenantBranding::logoUrl($tenant),
            'tenantName' => $tenant?->name,
            'eyebrow'    => $branding['eyebrow'] ?? 'CBSE Sahodaya School Complex',
            'tagline'    => $branding['tagline'] ?? null,
            'motto'      => $branding['motto'] ?? null,
            'phone'      => $branding['phone'] ?? null,
            'email'      => $branding['email'] ?? null,
            'sessionExpired' => request()->query('session') === 'expired',
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid credentials.']);
        }

        $user = $request->user()->fresh();

        if ($message = self::portalMismatchMessage($user, $request)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => $message]);
        }

        $request->session()->regenerate();

        if ($user->hasRole('school_admin') && ! $user->hasVerifiedEmail()) {
            $intended = $request->session()->pull('url.intended');
            if (is_string($intended) && str_contains($intended, '/email/verify/')) {
                return \App\Support\InertiaAuth::redirectTo($request, $intended);
            }

            return \App\Support\InertiaAuth::redirectTo($request, route('verification.notice'));
        }

        return \App\Support\InertiaAuth::intended($request, self::homeFor($user));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function verifyNotice(Request $request): Response|RedirectResponse
    {
        $user = $request->user()?->fresh();

        if (! $user) {
            return \App\Support\InertiaAuth::redirectToLogin(
                $request,
                'Your session has expired. Please sign in again.',
            );
        }

        if ($user->hasVerifiedEmail()) {
            Auth::login($user);
            $request->session()->regenerate();

            return \App\Support\InertiaAuth::intended($request, self::homeFor($user));
        }

        $tenant = TenantBranding::resolveTenant($request);
        $branding = ($tenant && $tenant->type === 'sahodaya')
            ? SahodayaHomepageContent::get($tenant)
            : [];

        return inertia('Auth/VerifyEmail', [
            'logoUrl'    => TenantBranding::logoUrl($tenant),
            'tenantName' => $tenant?->name,
            'portalUrl'  => $tenant ? TenantDomainSync::publicUrl($tenant) : null,
            'eyebrow'    => $branding['eyebrow'] ?? null,
        ]);
    }

    public function resendVerification(Request $request)
    {
        $user = $request->user()?->fresh();

        if (! $user) {
            return \App\Support\InertiaAuth::redirectToLogin(
                $request,
                'Your session has expired. Please sign in again.',
            );
        }

        if ($user->hasVerifiedEmail()) {
            Auth::login($user);

            return \App\Support\InertiaAuth::intended($request, self::homeFor($user));
        }

        $this->sendVerificationFor($user);

        return back()->with('success', 'Verification link sent to your Gmail.');
    }

    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'This verification link is invalid or has expired.');
        }

        $user = User::query()->findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'This verification link is invalid.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->intended(self::homeFor($user))
            ->with('success', 'Gmail verified successfully. Welcome!');
    }

    public static function homeFor(User $user): string
    {
        if ($user->isSuperAdmin()) {
            return route('admin.dashboard');
        }

        if ($user->hasRole('sahodaya_admin') && $user->tenant_id) {
            return "/sahodaya-admin/{$user->tenant_id}";
        }

        if ($user->hasRole('school_admin') && $user->tenant_id) {
            return "/school-admin/{$user->tenant_id}";
        }

        return route('admin.dashboard');
    }

    private static function sendVerificationFor(User $user): void
    {
        if ($user->hasRole('school_admin') && $user->tenant_id) {
            $school = \App\Models\Tenant::find($user->tenant_id);
            if ($school?->parent_id) {
                SahodayaMailer::for($school->parent_id)->sendVerification($user);

                return;
            }
        }

        $user->sendEmailVerificationNotification();
    }

    private static function portalMismatchMessage(User $user, Request $request): ?string
    {
        $host = strtolower($request->getHost());

        if (TenantDomainSync::isCentralHost($host)) {
            return $user->isSuperAdmin() ? null : 'School and Sahodaya admins must sign in on their portal website, not the superadmin site.';
        }

        $hostTenant = TenantBranding::resolveTenant($request);
        if (! $hostTenant) {
            return null;
        }

        if ($hostTenant->type === 'sahodaya') {
            if ($user->hasRole('sahodaya_admin')) {
                return $user->tenant_id === $hostTenant->id
                    ? null
                    : 'This Sahodaya admin account belongs to another cluster. Use your own Sahodaya portal URL.';
            }

            if ($user->hasRole('school_admin')) {
                $school = Tenant::find($user->tenant_id);

                return $school?->parent_id === $hostTenant->id
                    ? null
                    : 'This school account belongs to another Sahodaya. Sign in on your Sahodaya portal (link in your registration email).';
            }
        }

        if ($hostTenant->type === 'school') {
            if ($user->hasRole('school_admin')) {
                return $user->tenant_id === $hostTenant->id
                    ? null
                    : 'This login is for a different school. Use your school\'s Sahodaya portal to sign in.';
            }

            if ($user->hasRole('sahodaya_admin')) {
                return $user->tenant_id === $hostTenant->parent_id ? null : 'Invalid credentials.';
            }
        }

        return null;
    }
}
