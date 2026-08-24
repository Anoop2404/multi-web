<?php

namespace App\Jobs;

use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\UserCredentialService;
use App\Services\Mail\SahodayaMailer;
use App\Services\Portal\TeacherPortalProvisioner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionTeacherPortalUsersJob implements ShouldQueue
{
    use Queueable;

    /** @param list<int> $teacherIds */
    public function __construct(
        public array $teacherIds,
        public bool $includeExisting = false,
        public ?int $requestedByUserId = null,
    ) {}

    public function handle(TeacherPortalProvisioner $provisioner, UserCredentialService $credentialService): void
    {
        $teachers = Teacher::whereIn('id', $this->teacherIds)->get();

        foreach ($teachers as $teacher) {
            $email = $teacher->email;
            if (! $email) {
                $cleanCode = preg_replace('/[^a-zA-Z0-9]/', '', $teacher->reg_no ?: "t{$teacher->id}");
                $email = strtolower("teacher_{$cleanCode}@sahodaya.org");
            }

            try {
                if (! $teacher->user_id) {
                    $result = $provisioner->provision($teacher, $email);
                    $password = $result['password'];
                } elseif ($this->includeExisting) {
                    $user = User::find($teacher->user_id);
                    if ($user) {
                        $result = $credentialService->resetPassword($user, $this->requestedByUserId);
                        $password = $result['password'];
                    } else {
                        continue;
                    }
                } else {
                    continue;
                }

                $teacherFresh = $teacher->fresh();
                $teacherFresh->loadMissing('user');
                $user = $teacherFresh->user;
                $school = Tenant::find($teacherFresh->tenant_id);
                $sahodayaId = $school?->parent_id ?: ($school?->id ?: $teacherFresh->tenant_id);

                if ($user && $email && $sahodayaId) {
                    $username = $teacherFresh->login_code ?? $user->username ?? '';
                    $schoolName = $school?->name ?? 'Sahodaya';

                    SahodayaMailer::for($sahodayaId)->sendView(
                        $email,
                        "Your Teacher Portal Credentials - {$schoolName}",
                        'emails.teacher-credentials',
                        [
                            'teacher'       => $teacherFresh,
                            'school'        => $school,
                            'user'          => $user,
                            'username'      => $username,
                            'plainPassword' => $password,
                            'loginUrl'      => url('/portal/login'),
                        ]
                    );
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
