<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Services\Membership\SahodayaSetupService;
use Illuminate\Http\Request;

class SetupWizardController extends SahodayaAdminController
{
    public function show(SahodayaSetupService $setup)
    {
        if ($this->isStaff) {
            return redirect("/sahodaya-admin/{$this->sahodaya->id}");
        }

        $checklist = $setup->checklist($this->sahodaya);

        return $this->inertia('Sahodaya/Setup/Wizard', [
            'checklist'       => $checklist,
            'completedCount'  => collect($checklist)->where('done', true)->count(),
            'totalSteps'      => count($checklist),
            'setupComplete'   => $setup->isComplete($this->sahodaya),
        ]);
    }

    public function complete(SahodayaSetupService $setup)
    {
        abort_if($this->isStaff, 403);

        if (! $setup->isComplete($this->sahodaya)) {
            return back()->withErrors(['setup' => 'Complete all required steps before finishing setup.']);
        }

        $setup->markComplete($this->sahodaya);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}")
            ->with('success', 'Your Sahodaya is ready! You can now invite schools and create events.');
    }

    public function dismiss(SahodayaSetupService $setup)
    {
        abort_if($this->isStaff, 403);

        $setup->dismiss($this->sahodaya);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}");
    }
}
