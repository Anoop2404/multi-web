<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\SahodayaProfile;
use App\Support\SchoolApplicationForm;
use Illuminate\Http\Request;

class SchoolSetupController extends SchoolAdminController
{
    public function code()
    {
        $sahodaya = $this->school->parent;
        $profile = $sahodaya
            ? SahodayaProfile::where('tenant_id', $sahodaya->id)->first()
            : null;

        $sahodayaPrefix = $profile?->prefix;
        $schoolCode = $this->school->school_prefix;
        $example = ($sahodayaPrefix && $schoolCode)
            ? strtoupper($sahodayaPrefix).'/'.strtoupper($schoolCode).'/26/0001'
            : null;

        return $this->inertia('School/Setup/Code', [
            'schoolCode'     => $schoolCode,
            'codeLocked'     => (bool) $this->school->prefixes_locked,
            'suggestedCode'  => $this->suggestedCode(),
            'regNoExample'   => $example,
            'sahodayaPrefix' => $sahodayaPrefix,
        ]);
    }

    public function saveCode(Request $request)
    {
        abort_if($this->school->prefixes_locked && filled($this->school->school_prefix), 403, 'School code is locked.');

        $sahodaya = $this->school->parent;
        abort_unless($sahodaya, 422, 'School is not linked to a Sahodaya.');

        $data = $request->validate([
            'school_prefix' => SchoolApplicationForm::schoolPrefixRules($sahodaya, $this->school->id),
        ]);

        $this->school->update([
            'school_prefix' => strtoupper(trim($data['school_prefix'])),
        ]);

        return redirect("/school-admin/{$this->school->id}")
            ->with('success', 'School code saved. You can now set up classes and register students.');
    }

    private function suggestedCode(): string
    {
        return strtoupper(substr(preg_replace('/[^a-z]/i', '', $this->school->name), 0, 3)) ?: 'SCH';
    }
}
