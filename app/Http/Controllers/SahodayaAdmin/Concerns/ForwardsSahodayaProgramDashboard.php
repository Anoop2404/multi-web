<?php

namespace App\Http\Controllers\SahodayaAdmin\Concerns;

use App\Http\Controllers\SahodayaAdmin\FestEventController;

trait ForwardsSahodayaProgramDashboard
{
    abstract protected function sahodayaProgramSlug(): string;

    public function dashboard(string $tenantId)
    {
        return app(FestEventController::class)->programIndex($tenantId, $this->sahodayaProgramSlug());
    }
}
