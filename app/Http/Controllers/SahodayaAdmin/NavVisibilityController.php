<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\SahodayaProfile;
use App\Services\Audit\DataChangeLogger;
use App\Support\SahodayaNavVisibility;
use Illuminate\Http\Request;

class NavVisibilityController extends SahodayaAdminController
{
    public function edit()
    {
        $profile = SahodayaProfile::firstOrCreate(
            ['tenant_id' => $this->sahodaya->id],
            ['student_data_mode' => 'not_required', 'membership_fee_type' => 'fixed'],
        );

        return $this->inertia('Sahodaya/Settings/NavVisibility', [
            'visibility'     => SahodayaNavVisibility::forProfile($profile),
            'programLabels'  => SahodayaNavVisibility::programLabels(),
            'menuLabels'     => SahodayaNavVisibility::menuLabels(),
            'programSlugs'   => SahodayaNavVisibility::programSlugs(),
        ]);
    }

    public function update(Request $request)
    {
        $profile = SahodayaProfile::firstOrCreate(
            ['tenant_id' => $this->sahodaya->id],
            ['student_data_mode' => 'not_required', 'membership_fee_type' => 'fixed'],
        );

        $data = $request->validate([
            'programs'   => 'required|array',
            'programs.*' => 'boolean',
            'menus'      => 'required|array',
            'menus.*'    => 'boolean',
        ]);

        $normalized = SahodayaNavVisibility::normalizeInput($data);
        $profile->update(['nav_visibility' => $normalized]);

        app(DataChangeLogger::class)->event(
            'updated',
            'Sidebar menu visibility updated',
            $this->sahodaya->id,
            'settings',
            $profile,
            ['nav_visibility' => $normalized],
        );

        return back()->with('success', 'Sidebar visibility saved.');
    }
}
