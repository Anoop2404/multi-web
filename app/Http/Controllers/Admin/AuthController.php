<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\SahodayaMailer;
use App\Support\SahodayaHomepageContent;
use App\Support\TenantBranding;
use App\Support\TenantDomainSync;
use App\Support\TenantUserCatalog;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\LoginLockoutService;
use App\Support\InertiaAuth;
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

    public function showSchoolLogin(): Response|RedirectResponse
    {
        if (TenantDomainSync::isCentralHost(request()->getHost())) {
            return redirect()->route('login');
        }

        $tenant = TenantBranding::resolveTenant();
        if (! $tenant || $tenant->type !== 'sahodaya') {
            return redirect()->route('login');
        }

        $branding = SahodayaHomepageContent::get($tenant);

        return inertia('Auth/SchoolLogin', [
            'logoUrl'    => TenantBranding::logoUrl($tenant),
            'tenantName' => $tenant->name,
            'motto'      => $branding['motto'] ?? null,
            'phone'      => $branding['phone'] ?? null,
            'email'      => $branding['email'] ?? null,
            'showRegisterLink' => true,
            'sessionExpired' => request()->query('session') === 'expired',
        ]);
    }

    public function showPortalLogin(): Response|RedirectResponse
    {
        if (TenantDomainSync::isCentralHost(request()->getHost())) {
            return redirect()->route('login');
        }

        $tenant = TenantBranding::resolveTenant();
        if (! $tenant || ! in_array($tenant->type, ['sahodaya', 'school'], true)) {
            return redirect()->route('login');
        }

        $branding = $tenant->type === 'sahodaya'
            ? SahodayaHomepageContent::get($tenant)
            : [];

        return inertia('Auth/PortalLogin', [
            'logoUrl'        => TenantBranding::logoUrl($tenant),
            'tenantName'     => $tenant->name,
            'motto'          => $branding['motto'] ?? null,
            'sessionExpired' => request()->query('session') === 'expired',
        ]);
    }

    public function showForgotPassword(): Response|RedirectResponse
    {
        if (TenantDomainSync::isCentralHost(request()->getHost())) {
            return redirect()->route('login');
        }

        return inertia('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = \Illuminate\Support\Facades\Password::sendResetLink(
            ['email' => strtolower(trim($request->input('email')))],
        );

        return $status === \Illuminate\Support\Facades\Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function showResetPassword(Request $request, string $token): Response
    {
        return inertia('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $status = \Illuminate\Support\Facades\Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password'             => $password,
                    'plain_password'       => null,
                    'must_change_password' => false,
                ])->save();
            },
        );

        return $status === \Illuminate\Support\Facades\Password::PASSWORD_RESET
            ? redirect()->route('portal.login')->with('success', 'Password reset. You can sign in now.')
            : back()->withErrors(['email' => __($status)]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|string',
            'password' => 'required',
        ]);

        $identifier = trim($data['email']);
        $field = str_contains($identifier, '@') ? 'email' : 'username';
        if ($field === 'email') {
            $identifier = strtolower($identifier);
        }

        $credentials = [$field => $identifier, 'password' => $data['password']];
        $auditContext = self::auditContext($request);
        $lockout = app(LoginLockoutService::class);

        if ($lockout->isLocked($identifier)) {
            return self::authErrorResponse($request, $lockout->lockoutMessage($identifier));
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $lockout->recordFailedAttempt($identifier);
            app(PlatformAuditLogger::class)->loginFailed(
                $identifier,
                'invalid_credentials',
                context: $auditContext,
            );

            return self::authErrorResponse(
                $request,
                'Invalid username/email or password. Please check your credentials and try again.',
            );
        }

        $lockout->clear($identifier);

        $user = $request->user()->fresh();

        if ($message = self::portalMismatchMessage($user, $request)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            app(PlatformAuditLogger::class)->loginPortalRejected(
                $user->id,
                $user->email,
                $message,
                context: $auditContext,
            );

            return self::authErrorResponse($request, $message);
        }

        $request->session()->regenerate();

        $user->update(['last_login_at' => now()]);

        app(PlatformAuditLogger::class)->login($user->id, $user->email, context: $auditContext);

        if ($user->must_change_password) {
            return redirect()->route('password.change');
        }

        if ($user->hasAnyRole(['school_admin', 'school_principal', 'school_vice_principal']) && ! $user->hasVerifiedEmail()) {
            $intended = $request->session()->pull('url.intended');
            if (is_string($intended) && str_contains($intended, '/email/verify/')) {
                return redirect()->to($intended);
            }

            return redirect()->route('verification.notice');
        }

        $home = self::homeFor($user);
        if ($home === null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            app(PlatformAuditLogger::class)->loginNoPortal($user->id, $user->email, context: $auditContext);

            return self::authErrorResponse(
                $request,
                'Your account has no portal assigned. Contact your administrator.',
            );
        }

        return InertiaAuth::intended($request, $home);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            app(PlatformAuditLogger::class)->logout(
                $user->id,
                $user->email,
                context: self::auditContext($request),
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** @return array<string, mixed> */
    private static function auditContext(Request $request): array
    {
        $hostTenant = TenantBranding::resolveTenant($request);

        return [
            'host'        => $request->getHost(),
            'portal'      => match (true) {
                $request->routeIs('school.login') || str_contains($request->headers->get('referer', ''), 'school-login') => 'school',
                $request->routeIs('portal.login') || str_contains($request->headers->get('referer', ''), 'portal/login') => 'portal',
                TenantDomainSync::isCentralHost($request->getHost()) => 'superadmin',
                default => 'sahodaya',
            },
            'tenant_id'   => $hostTenant?->id,
            'tenant_type' => $hostTenant?->type,
            'user_agent'  => substr((string) $request->userAgent(), 0, 255),
        ];
    }

    private static function authErrorResponse(Request $request, string $message): RedirectResponse
    {
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => $message]);
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

        $user = TenantDomainSync::isCentralHost($request->getHost())
            ? PlatformUser::query()->findOrFail($id)
            : User::query()->findOrFail($id);

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

    public static function homeFor(User|PlatformUser $user): ?string
    {
        if ($user->isSuperAdmin()) {
            return route('admin.dashboard');
        }

        if ($user->hasAnyRole(['state_admin', 'state_staff'])) {
            return route('admin.state.dashboard');
        }

        if ($user->hasRole('sahodaya_admin') && $user->tenant_id) {
            return "/sahodaya-admin/{$user->tenant_id}";
        }

        if ($user->hasRole('sahodaya_staff') && $user->tenant_id) {
            return "/sahodaya-admin/{$user->tenant_id}";
        }

        if ($user->hasAnyRole([
            'registration_coordinator',
            'event_coordinator',
            'sahodaya_finance',
            'certificate_collector',
            'data_entry',
            'mark_entry_admin',
        ]) && $user->tenant_id) {
            return "/sahodaya-admin/{$user->tenant_id}";
        }

        if ($user->hasAnyRole(['school_admin', 'school_principal', 'school_vice_principal']) && $user->tenant_id) {
            return "/school-admin/{$user->tenant_id}";
        }

        if ($user->hasRole('school_event_coordinator') && $user->tenant_id) {
            return app(\App\Services\School\SchoolUserScopeService::class)->homeUrlFor($user, $user->tenant_id);
        }

        if ($user->hasRole('school_sports_coordinator') && $user->tenant_id) {
            return "/school-admin/{$user->tenant_id}/sports";
        }

        if ($user->hasRole('school_kalotsavam_coordinator') && $user->tenant_id) {
            return "/school-admin/{$user->tenant_id}/kalotsav";
        }

        if ($user->hasRole('school_mcq_coordinator') && $user->tenant_id) {
            return "/school-admin/{$user->tenant_id}/mcq";
        }

        if ($user->hasRole('school_training_coordinator') && $user->tenant_id) {
            return "/school-admin/{$user->tenant_id}/training";
        }

        if ($user->hasRole('school_finance_coordinator') && $user->tenant_id) {
            return "/school-admin/{$user->tenant_id}/payments";
        }

        if ($user->hasRole('school_staff') && $user->tenant_id) {
            return "/school-admin/{$user->tenant_id}";
        }

        if ($user->hasRole('group_admin') && $user->tenant_id) {
            return "/portal/group/{$user->tenant_id}";
        }

        if ($user->hasRole('house_admin') && $user->tenant_id) {
            return "/portal/house-admin/{$user->tenant_id}";
        }

        if ($user->hasRole('student') && $user->tenant_id) {
            return "/portal/student/{$user->tenant_id}";
        }

        if ($user->hasRole('teacher') && $user->tenant_id) {
            return "/portal/teacher/{$user->tenant_id}";
        }

        if ($user->hasAnyRole(['mark_entry_coordinator', 'mark_entry_admin']) && $user->tenant_id) {
            return "/portal/fest-coordinator/{$user->tenant_id}";
        }

        if ($user->hasAnyRole(['exam_controller', 'exam_staff']) && $user->tenant_id) {
            return "/portal/exam/{$user->tenant_id}";
        }

        if ($user->hasRole('judge') && $user->tenant_id) {
            return "/portal/judge/{$user->tenant_id}";
        }

        if ($user->hasRole('fest_ops') && $user->tenant_id) {
            return "/portal/fest-ops/{$user->tenant_id}";
        }

        return null;
    }

    private static function sendVerificationFor(User|PlatformUser $user): void
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

    private static function portalMismatchMessage(User|PlatformUser $user, Request $request): ?string
    {
        $host = strtolower($request->getHost());

        if (TenantDomainSync::isCentralHost($host)) {
            if ($user->isSuperAdmin()) {
                return null;
            }
            if ($user->hasAnyRole(['state_admin', 'state_staff'])) {
                return null; // state admins log in at the central domain
            }
            if ($user->hasAnyRole(array_merge(
                ['sahodaya_staff', 'school_staff', 'judge', 'exam_controller', 'exam_staff', 'mark_entry_admin', 'mark_entry_coordinator', 'group_admin', 'house_admin', 'fest_ops'],
                array_diff(TenantUserCatalog::sahodayaPermissionRoles(), ['sahodaya_staff']),
            ))) {
                return 'This account must sign in on your Sahodaya or school portal website, not the superadmin site.';
            }

            return 'School and Sahodaya admins must sign in on their portal website, not the superadmin site.';
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

            if ($user->hasRole('sahodaya_staff')
                || $user->hasAnyRole(array_diff(TenantUserCatalog::sahodayaAdminPanelRoles(), ['sahodaya_admin', 'sahodaya_staff']))) {
                return $user->tenant_id === $hostTenant->id
                    ? null
                    : 'This Sahodaya staff account belongs to another cluster. Use your own Sahodaya portal URL.';
            }

            if ($user->hasAnyRole(TenantUserCatalog::sahodayaPortalOnlyRoles())) {
                return $user->tenant_id === $hostTenant->id
                    ? null
                    : 'This account belongs to another Sahodaya cluster. Sign in on your assigned portal URL.';
            }

            if ($user->hasRole('school_admin')) {
                $school = Tenant::find($user->tenant_id);

                return $school?->parent_id === $hostTenant->id
                    ? null
                    : 'This school account belongs to another Sahodaya. Sign in on your Sahodaya portal (link in your registration email).';
            }

            if ($user->hasAnyRole(['teacher', 'student', 'group_admin', 'house_admin'])) {
                $school = Tenant::find($user->tenant_id);

                return $school?->parent_id === $hostTenant->id
                    ? null
                    : 'This account belongs to another Sahodaya cluster. Sign in on your school portal URL.';
            }
        }

        if ($hostTenant->type === 'school') {
            if ($user->hasRole('school_admin')) {
                return $user->tenant_id === $hostTenant->id
                    ? null
                    : 'This login is for a different school. Use your school\'s Sahodaya portal to sign in.';
            }

            if ($user->hasRole('school_staff')) {
                return $user->tenant_id === $hostTenant->id
                    ? null
                    : 'This school staff account belongs to another school.';
            }

            if ($user->hasRole('sahodaya_admin')) {
                return $user->tenant_id === $hostTenant->parent_id ? null : 'Invalid credentials.';
            }

            if ($user->hasRole('group_admin')) {
                return $user->tenant_id === $hostTenant->id
                    ? null
                    : 'This group admin account belongs to another school.';
            }

            if ($user->hasRole('house_admin')) {
                return $user->tenant_id === $hostTenant->id
                    ? null
                    : 'This house admin account belongs to another school.';
            }

            if ($user->hasRole('student') || $user->hasRole('teacher')) {
                return $user->tenant_id === $hostTenant->id ? null : 'This account belongs to another school.';
            }
        }

        return null;
    }
}
