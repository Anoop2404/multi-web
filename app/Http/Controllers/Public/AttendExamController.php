<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mcq\McqExamSessionService;
use App\Services\Mcq\McqVenueKeycodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Venue / lab join: student enters a per-registration keycode and is signed into
 * the portal exam page without typing their portal password.
 */
class AttendExamController extends Controller
{
    public function show(Request $request)
    {
        return inertia('Public/AttendExam', [
            'keycode' => strtoupper(trim((string) $request->query('code', ''))),
            'tenantName' => tenant()?->name,
        ]);
    }

    public function store(
        Request $request,
        McqVenueKeycodeService $keycodes,
        McqExamSessionService $sessions,
    ) {
        $data = $request->validate([
            'keycode' => ['required', 'string', 'min:6', 'max:32'],
        ]);

        $registration = $keycodes->findByCode($data['keycode']);
        if (! $registration) {
            throw ValidationException::withMessages([
                'keycode' => 'Invalid keycode. Check the code on your seat slip or ask the invigilator.',
            ]);
        }

        $exam = $registration->exam;
        if (! $exam || ! $exam->allowsVenueKeycodeJoin()) {
            throw ValidationException::withMessages([
                'keycode' => 'Venue keycode join is not enabled for this exam.',
            ]);
        }

        if (\App\Services\ExamEngine\ExamEngineRun::usesEngine($exam)) {
            throw ValidationException::withMessages([
                'keycode' => 'This exam uses the online exam site. Sign in there with your hall ticket and passcode.',
            ]);
        }

        $student = $registration->student;
        $user = $student?->user;
        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'keycode' => 'This registration has no portal login. Contact your school.',
            ]);
        }

        if ($registration->status === 'submitted' && $registration->canReattempt()) {
            $sessions->beginReattempt($registration);
            $registration->refresh();
        }

        try {
            $sessions->assertCanStart($registration);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'This exam cannot be started.';
            throw ValidationException::withMessages(['keycode' => $message]);
        }

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        $schoolId = $registration->school_id ?: $user->tenant_id;

        return redirect()->route('portal.student.mcq.exam', [
            'tenantId' => $schoolId,
            'registration' => $registration->id,
        ]);
    }
}
