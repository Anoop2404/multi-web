<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Support\SahodayaPublicData;

class MemberSchoolsController extends Controller
{
    use RendersPublicPages;

    public function index()
    {
        $tenant = $this->resolveTenant();
        // A member-school directory only makes sense for a Sahodaya cluster —
        // an individual school has no members of its own.
        abort_unless($tenant->type === 'sahodaya', 404);

        $schools = SahodayaPublicData::memberSchools($tenant->id);

        return $this->renderPublic('public.member-schools.index', $tenant, [
            'schools'   => $schools,
            'districts' => $schools->pluck('district')->filter()->unique()->sort()->values(),
            'pageSeo'   => [
                'title'       => 'Member Schools — '.$tenant->name,
                'description' => 'Browse the member schools of '.$tenant->name.'.',
                'og_type'     => 'website',
            ],
        ]);
    }
}
