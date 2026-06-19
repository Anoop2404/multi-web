<?php

namespace App\Http\Controllers\Api\V1\Sahodaya;

use App\Models\SahodayaProfile;

class SettingsApiController extends SahodayaApiController
{
    public function show()
    {
        $profile = SahodayaProfile::where('tenant_id', $this->sahodaya->id)->first();

        return $this->ok([
            'prefix'                      => $profile?->prefix,
            'student_data_mode'           => $profile?->student_data_mode,
            'teacher_registration_enabled'=> $profile?->teacher_registration_enabled,
            'membership_fee_type'         => $profile?->membership_fee_type,
            'fixed_membership_fee_amount' => $profile?->fixed_membership_fee_amount,
            'payment_details_text'        => $profile?->paymentDetailsText(),
        ]);
    }
}
