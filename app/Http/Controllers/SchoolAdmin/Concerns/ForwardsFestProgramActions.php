<?php

namespace App\Http\Controllers\SchoolAdmin\Concerns;

use App\Http\Controllers\SchoolAdmin\FestRegistrationController;
use App\Http\Controllers\SchoolAdmin\FestSchoolReportController;
use App\Support\ProgramRouteMap;
use Illuminate\Http\Request;

/** Forwards dedicated event-type routes to existing fest program controllers. */
trait ForwardsFestProgramActions
{
    abstract protected function festProgramPrefix(): string;

    protected function festProgramSlug(): string
    {
        return ProgramRouteMap::slugFromPrefix($this->festProgramPrefix());
    }

    public function hub(string $tenantId)
    {
        return app(FestRegistrationController::class)->programHub($tenantId, $this->festProgramSlug());
    }

    public function registration(Request $request, string $tenantId)
    {
        return app(FestRegistrationController::class)->index($request, $tenantId, $this->festProgramSlug(), 'registration');
    }

    public function results(Request $request, string $tenantId)
    {
        return app(FestRegistrationController::class)->index($request, $tenantId, $this->festProgramSlug(), 'results');
    }

    public function reports(Request $request, string $tenantId)
    {
        return app(FestSchoolReportController::class)->index($request, $tenantId, $this->festProgramSlug());
    }

    public function qualifiers(Request $request, string $tenantId)
    {
        return app(FestSchoolReportController::class)->qualifiers($request, $tenantId, $this->festProgramSlug());
    }

    public function myEvents(string $tenantId)
    {
        return redirect("/school-admin/{$tenantId}/fest-programs?type=".ProgramRouteMap::eventTypeFromPrefix($this->festProgramPrefix()));
    }
}
