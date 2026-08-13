<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformUser;
use App\Models\User;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;

class DevPassTokenAdminController extends Controller
{
    public function show(Request $request): Response
    {
        $activeToken = config('auth.dev_pass_token') ?: env('DEV_LOGIN_PASS_TOKEN');
        $search = trim((string) $request->query('search', ''));

        $searchResults = [];
        if ($search !== '') {
            $matchingUsers = User::query()
                ->where('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->with('tenant:id,name')
                ->take(10)
                ->get()
                ->map(fn (User $u) => [
                    'id'          => $u->id,
                    'name'        => $u->name,
                    'email'       => $u->email,
                    'username'    => $u->username,
                    'type'        => 'Tenant User',
                    'tenant_name' => $u->tenant?->name ?? '—',
                ]);

            $matchingPlatform = PlatformUser::query()
                ->where('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->take(10)
                ->get()
                ->map(fn (PlatformUser $u) => [
                    'id'          => $u->id,
                    'name'        => $u->name,
                    'email'       => $u->email,
                    'username'    => $u->username,
                    'type'        => 'Superadmin / Platform User',
                    'tenant_name' => 'Central Platform',
                ]);

            $searchResults = $matchingUsers->concat($matchingPlatform)->values()->all();
        }

        return inertia('DevPassToken', [
            'activeToken'   => $activeToken,
            'search'        => $search,
            'searchResults' => $searchResults,
            'status'        => session('status'),
            'success'       => session('success'),
        ]);
    }

    public function update(Request $request, PlatformAuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'dev_pass_token' => 'nullable|string|min:4|max:100',
        ]);

        $token = trim((string) ($data['dev_pass_token'] ?? ''));

        $this->updateEnvFile($token);
        config(['auth.dev_pass_token' => $token !== '' ? $token : null]);

        $audit->log(
            'dev_pass_token_updated',
            "Superadmin updated developer login pass token",
            properties: [
                'updated_by' => $request->user()?->id,
                'is_enabled' => $token !== '',
            ]
        );

        return back()->with('success', $token !== ''
            ? "Developer Login Pass Token updated to: {$token}"
            : 'Developer Login Pass Token disabled.');
    }

    public function regenerate(Request $request, PlatformAuditLogger $audit): RedirectResponse
    {
        $newToken = 'dev-pass-' . Str::lower(Str::random(16));

        $this->updateEnvFile($newToken);
        config(['auth.dev_pass_token' => $newToken]);

        $audit->log(
            'dev_pass_token_regenerated',
            "Superadmin regenerated developer login pass token",
            properties: [
                'updated_by' => $request->user()?->id,
                'new_token'  => $newToken,
            ]
        );

        return back()->with('success', "New Developer Login Pass Token generated: {$newToken}");
    }

    private function updateEnvFile(string $token): void
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);
        if (preg_match('/^DEV_LOGIN_PASS_TOKEN=.*/m', $content)) {
            $content = preg_replace('/^DEV_LOGIN_PASS_TOKEN=.*/m', "DEV_LOGIN_PASS_TOKEN={$token}", $content);
        } else {
            $content .= "\nDEV_LOGIN_PASS_TOKEN={$token}\n";
        }

        file_put_contents($envPath, $content);
    }
}
