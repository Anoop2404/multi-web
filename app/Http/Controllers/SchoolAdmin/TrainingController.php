<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;

class TrainingController extends SchoolAdminController
{
    public function hub()
    {
        return app(TrainingRegistrationController::class)->index();
    }
}
