<?php

namespace App\Http\Controllers\Api\V1\School;

use App\Models\SahodayaProfile;
use App\Support\SchoolApplicationForm;
use Illuminate\Http\Request;

class SetupApiController extends SchoolApiController
{
    public function show()
    {
        $sahodaya = $this->school->parent;
        $profile = $sahodaya
            ? SahodayaProfile::where('tenant_id', $sahodaya->id)->first()
            : null;

        $sahodayaPrefix = $profile?->prefix;
        $schoolCode = $this->school->school_prefix;

        return $this->ok([
            'school_code'     => $schoolCode,
            'code_locked'     => (bool) $this->school->prefixes_locked,
            'suggested_code'  => strtoupper(substr(preg_replace('/[^a-z]/i', '', $this->school->name), 0, 3)) ?: 'SCH',
            'reg_no_example'  => ($sahodayaPrefix && $schoolCode)
                ? strtoupper($sahodayaPrefix).'/'.strtoupper($schoolCode).'/26/0001'
                : null,
            'sahodaya_prefix' => $sahodayaPrefix,
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

        return $this->message('School code saved.', 200, [
            'school_prefix' => $this->school->fresh()->school_prefix,
        ]);
    }
}
