<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\SchoolAdmin\Concerns\ForwardsFestProgramActions;
use Illuminate\Http\Request;

class KalotsavController extends SchoolAdminController
{
    use ForwardsFestProgramActions;

    protected function festProgramPrefix(): string
    {
        return 'kalotsav';
    }

    /** Overrides ForwardsFestProgramActions::hub() — Kalotsav gets its own hub behavior. */
    public function hub(Request $request, string $tenantId)
    {
        return app(FestRegistrationController::class)->kalotsavHub($request, $tenantId);
    }
}
