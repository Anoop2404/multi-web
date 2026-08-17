<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ExternalSchool;
use App\Models\FestStateProgramItem;
use App\Models\State\StateQualifierEntry;
use App\Services\State\ExternalIntakeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * School portal for the outside-Sahodaya intake. Username+password login, session-based (see
 * EnsureExternalSchoolPortalAuth) rather than a Laravel guard. The original {code}-in-URL
 * access is kept as a permanent, quiet fallback for already-shared links (legacyCodeLogin).
 */
class ExternalSchoolPortalController extends Controller
{
    public function showLogin()
    {
        return view('external.school-login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $school = ExternalSchool::where('username', $data['username'])->first();

        if (! $school || ! $school->password || ! Hash::check($data['password'], $school->password)) {
            return back()->withErrors(['username' => 'Incorrect username or password.'])->onlyInput('username');
        }

        abort_unless($school->isActive(), 403, 'This account has been disabled. Contact your Sahodaya coordinator.');
        abort_unless($school->sahodaya?->isActive(), 403, 'Your Sahodaya\'s access has been disabled. Contact the State Kalolsavam office.');

        $this->establishSession($request, $school);

        return redirect()->route('state.external.school.show');
    }

    /** Permanent fallback for already-shared {code} links — establishes the same session a username/password login would. */
    public function legacyCodeLogin(Request $request, string $code)
    {
        $school = ExternalSchool::where('access_code', strtoupper($code))->first();

        abort_if(! $school, 404, 'Access code not recognized.');
        abort_unless($school->isActive(), 403, 'This access code has been disabled. Contact your Sahodaya coordinator.');
        abort_unless($school->sahodaya?->isActive(), 403, 'Your Sahodaya\'s access has been disabled. Contact the State Kalolsavam office.');

        $this->establishSession($request, $school);

        return redirect()->route('state.external.school.show');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('external_school_id');

        return redirect()->route('state.external.school.login');
    }

    public function show(Request $request)
    {
        $school = $request->attributes->get('externalSchool');

        return view('external.school-portal', [
            'school'  => $school->load('sahodaya.program'),
            'items'   => FestStateProgramItem::where('state_program_id', $school->sahodaya->state_program_id)
                ->orderBy('display_order')
                ->get(['id', 'item_code', 'title', 'class_group']),
            'entries' => StateQualifierEntry::where('school_id', $school->id)
                ->orderBy('item_code')
                ->get(),
        ]);
    }

    public function store(Request $request, ExternalIntakeService $service)
    {
        $school = $request->attributes->get('externalSchool');

        $data = $request->validate([
            'item_code'    => 'required|string|max:20',
            'item_name'    => 'nullable|string|max:255',
            'student_name' => 'required|string|max:255',
            'class_name'   => 'nullable|string|max:40',
            'position'     => 'nullable|integer|min:1|max:3',
            'grade'        => 'nullable|string|max:8',
        ]);

        // Item selection must come from the state catalog dropdown, not free text — keeps
        // item_code matching clean downstream (§2.2's "item catalog mismatch" open question).
        $item = FestStateProgramItem::where('state_program_id', $school->sahodaya->state_program_id)
            ->where('item_code', $data['item_code'])
            ->first();

        abort_unless($item, 422, 'Select an item from the list.');

        $service->addEntry($school, $data + [
            'item_id'       => $item->id,
            'item_name'     => $item->title,
            'qualify_count' => $item->qualify_count,
        ]);

        return back()->with('success', "Added {$data['student_name']} for {$item->title}.");
    }

    public function destroy(Request $request, StateQualifierEntry $entry, ExternalIntakeService $service)
    {
        $school = $request->attributes->get('externalSchool');

        $service->removeEntry($school, $entry);

        return back()->with('success', 'Entry removed.');
    }

    private function establishSession(Request $request, ExternalSchool $school): void
    {
        $request->session()->regenerate();
        $request->session()->put('external_school_id', $school->id);
    }
}
