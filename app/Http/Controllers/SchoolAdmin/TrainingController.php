<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Services\Training\TeacherTrainingEligibilityService;

class TrainingController extends SchoolAdminController
{
    public function hub(TeacherTrainingEligibilityService $eligibility)
    {
        return app(TrainingRegistrationController::class)->index($eligibility);
    }
}
