<?php

namespace App\Http\Controllers\SahodayaAdmin;

use Illuminate\Http\Request;

class ProgramPlaceholderController extends SahodayaAdminController
{
    /** @var array<string, array{label: string, icon: string, registration_hint: string, results_hint: string}> */
    public const PROGRAMS = [
        'kalotsav' => [
            'label'             => 'Kalotsav',
            'icon'              => '🎭',
            'registration_hint' => 'School registration for Kalotsav will open here when the event is announced.',
            'results_hint'      => 'Kalotsav results will be published here after the event.',
        ],
        'sports-meet' => [
            'label'             => 'Sports Meet',
            'icon'              => '🏅',
            'registration_hint' => 'Sports meet registration will open here when the schedule is published.',
            'results_hint'      => 'Sports meet results and standings will appear here after the event.',
        ],
        'kids-fest' => [
            'label'             => 'Kids Fest',
            'icon'              => '🎨',
            'registration_hint' => 'Kids fest registration will open here when the event is announced.',
            'results_hint'      => 'Kids fest results will be published here after the event.',
        ],
    ];

    public function show(Request $request, string $tenantId, string $program, string $view)
    {
        abort_unless(isset(self::PROGRAMS[$program]), 404);
        abort_unless(in_array($view, ['registration', 'results'], true), 404);

        return $this->inertia('Sahodaya/Programs/Placeholder', [
            'program' => $program,
            'view'    => $view,
            'meta'    => self::PROGRAMS[$program],
        ]);
    }
}
