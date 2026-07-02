<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\PersonalAccessToken;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\MobileAuthPayload;
use App\Support\SahodayaHomepageContent;
use App\Support\TenantBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function loginBranding(Request $request)
    {
        $tenant = TenantBranding::resolveTenant($request);
        $branding = ($tenant && $tenant->type === 'sahodaya')
            ? SahodayaHomepageContent::get($tenant)
            : [];

        $logoPath = TenantBranding::logoUrl($tenant);
        $logoUrl = $logoPath
            ? (str_starts_with($logoPath, 'http') ? $logoPath : url($logoPath))
            : null;

        return $this->ok([
            'logo_url'      => $logoUrl,
            'tenant_name'   => $tenant?->name ?? 'Admin Portal',
            'eyebrow'       => $branding['eyebrow'] ?? 'CBSE Sahodaya School Complex',
            'tagline'       => $branding['tagline'] ?? null,
            'motto'         => $branding['motto'] ?? null,
            'phone'         => $branding['phone'] ?? null,
            'email'         => $branding['email'] ?? null,
            'portal_url'    => $request->getSchemeAndHttpHost(),
            'register_url'  => $tenant && $tenant->type === 'sahodaya'
                ? url('/school-register')
                : null,
            'show_register' => $tenant && $tenant->type === 'sahodaya',
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'required|string|max:255',
        ]);

        $email = strtolower(trim($data['email']));
        $auditContext = [
            'portal'     => 'mobile_api',
            'host'       => $request->getHost(),
            'device'     => $data['device_name'],
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ];

        if (! Auth::attempt(['email' => $email, 'password' => $data['password']])) {
            app(PlatformAuditLogger::class)->loginFailed($email, 'invalid_credentials', context: $auditContext);

            throw ValidationException::withMessages([
                'email' => ['Invalid email or password. Please check your credentials and try again.'],
            ]);
        }

        $user = $request->user()->fresh();

        if ($message = MobileAuthPayload::assertCanLogin($user)) {
            Auth::logout();

            app(PlatformAuditLogger::class)->loginPortalRejected(
                $user->id,
                $user->email,
                $message,
                context: $auditContext,
            );

            throw ValidationException::withMessages([
                'email' => [$message],
            ]);
        }

        app(PlatformAuditLogger::class)->login($user->id, $user->email, context: $auditContext);

        $token = $user->createToken($data['device_name'])->plainTextToken;

        return $this->ok(array_merge(
            MobileAuthPayload::for($user),
            ['token' => $token],
        ));
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            app(PlatformAuditLogger::class)->logout($user->id, $user->email, context: [
                'portal' => 'mobile_api',
                'host'   => $request->getHost(),
            ]);
        }

        if ($bearer = $request->bearerToken()) {
            PersonalAccessToken::findToken($bearer)?->delete();
        } else {
            $request->user()?->currentAccessToken()?->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->message('Logged out.');
    }

    public function me(Request $request)
    {
        return $this->ok(MobileAuthPayload::for($request->user()->fresh()));
    }
}
