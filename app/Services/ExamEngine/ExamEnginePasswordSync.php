<?php

namespace App\Services\ExamEngine;

use App\Models\McqRegistration;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenancyDatabase;

/**
 * Keeps armed engine rosters in step with portal passwords (CONTRACT §2.2): when a student or
 * teacher's password changes while their exam is armed, the new bcrypt hash is pushed so the
 * exam site accepts it. Never blocks the password change itself.
 */
class ExamEnginePasswordSync
{
    public function __construct(private readonly ExamEngineBridge $bridge, private readonly ExamEngineClient $client) {}

    public function handle(User $user): void
    {
        if (! $user->wasChanged('password') || ! $this->client->isConfigured() || ! $user->tenant_id) {
            return;
        }

        try {
            $tenant = Tenant::find($user->tenant_id);
            if (! $tenant) {
                return;
            }

            TenancyDatabase::withTenantDatabase($tenant, function () use ($user) {
                $studentIds = Student::where('user_id', $user->id)->pluck('id');
                $teacherIds = Teacher::where('user_id', $user->id)->pluck('id');
                if ($studentIds->isEmpty() && $teacherIds->isEmpty()) {
                    return;
                }

                McqRegistration::query()
                    ->where(fn ($q) => $q->whereIn('student_id', $studentIds)->orWhereIn('teacher_id', $teacherIds))
                    ->active()
                    ->whereHas('exam', fn ($q) => $q->where('delivery_mode', 'online')->where('scheduled_at', '>', now()->subDay()))
                    ->with('exam')
                    ->get()
                    ->filter(fn (McqRegistration $r) => ExamEngineRun::usesEngine($r->exam) && ExamEngineRun::state($r->exam) === 'armed')
                    ->each(fn (McqRegistration $r) => $this->bridge->pushRosterEntry($r));
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
