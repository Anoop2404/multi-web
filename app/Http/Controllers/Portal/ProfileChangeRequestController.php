<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Users\UserProfileChangeService;
use Illuminate\Http\Request;

class ProfileChangeRequestController extends Controller
{
    public function store(Request $request, UserProfileChangeService $service)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $service->submit($user, $request, $user->tenant_id);

        return back()->with('success', 'Profile change submitted for school review.');
    }
}
