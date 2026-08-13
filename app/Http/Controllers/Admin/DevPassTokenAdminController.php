<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class DevPassTokenAdminController extends Controller
{
    public function show(): Response
    {
        $activeToken = config('auth.dev_pass_token') ?: env('DEV_LOGIN_PASS_TOKEN');

        return inertia('Admin/DevPassToken', [
            'activeToken' => $activeToken,
            'status'      => session('status'),
            'success'     => session('success'),
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
