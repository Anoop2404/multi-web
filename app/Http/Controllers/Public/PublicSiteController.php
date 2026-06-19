<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use Illuminate\Http\Request;

class PublicSiteController extends Controller
{
    use RendersPublicPages;

    public function home(Request $request)
    {
        $tenant = $this->resolveTenant();

        return $this->renderPublic('public.home', $tenant, [
            'sections' => $tenant->sections()->active()->get(),
        ]);
    }
}
