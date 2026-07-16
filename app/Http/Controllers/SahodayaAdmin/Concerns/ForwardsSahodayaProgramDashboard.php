<?php

namespace App\Http\Controllers\SahodayaAdmin\Concerns;

use App\Http\Controllers\SahodayaAdmin\FestEventController;
use Illuminate\Http\Request;

trait ForwardsSahodayaProgramDashboard
{
    abstract protected function sahodayaProgramSlug(): string;

    public function dashboard(Request $request, string $tenantId)
    {
        return app(FestEventController::class)->programIndex($request, $tenantId, $this->sahodayaProgramSlug());
    }
}
