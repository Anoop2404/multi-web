<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Support\SahodayaPublicData;

class OfficeBearersController extends Controller
{
    use RendersPublicPages;

    public function index()
    {
        $tenant = $this->resolveTenant();

        return $this->renderPublic('public.office-bearers.index', $tenant, [
            'bearers' => SahodayaPublicData::officeBearers($tenant->id),
            'pageSeo' => [
                'title'       => 'Office Bearers — '.$tenant->name,
                'description' => 'Meet the office bearers and leadership team of '.$tenant->name.'.',
                'og_type'     => 'website',
            ],
        ]);
    }
}
