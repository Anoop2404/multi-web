<?php

namespace Database\Seeders;

use App\Models\AcademicYearRecord;
use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\FinancialYear;
use App\Models\McqExam;
use App\Models\McqExamStaff;
use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Auth\TenantUserProvisioner;
use App\Services\Mcq\McqSchoolFeeService;
use App\Support\TenantUserCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ScopedDemoAdminUsersSeeder extends Seeder
{
    private const SAHODAYA_ID = '3bf5cd42-d04a-445c-bec4-92dd45f5dc28';

    private const PASSWORD = '123@Admin';

    public function run(): void
    {
        $sahodaya = Tenant::query()
            ->where('type', 'sahodaya')
            ->find(self::SAHODAYA_ID);

        if (! $sahodaya) {
            throw new RuntimeException('The Malappuram Sahodaya demo tenant was not found.');
        }

        $sahodaya->run(function () use ($sahodaya): void {
            $this->call(TenantRolesAndPermissionsSeeder::class);
            $this->call(NotificationTemplatesSeeder::class);

            DB::transaction(function () use ($sahodaya): void {
                $academicYear = AcademicYearRecord::query()->active()->first();
                if (! $academicYear) {
                    throw new RuntimeException('No active academic year is configured.');
                }

                FinancialYear::updateOrCreate(
                    ['label' => '2026-27'],
                    [
                        'start_date' => '2026-04-01',
                        'end_date' => '2027-03-31',
                        'is_current' => true,
                    ],
                );

                $englishFest = FestEvent::query()
                    ->where('tenant_id', $sahodaya->id)
                    ->where('event_type', 'english_fest')
                    ->whereNull('parent_event_id')
                    ->firstOrFail();

                $regions = FestEvent::query()
                    ->where('parent_event_id', $englishFest->id)
                    ->where('partition_role', 'region')
                    ->orderBy('id')
                    ->get();

                if ($regions->count() < 2) {
                    throw new RuntimeException('The English Fest must have at least two regional partitions.');
                }

                $exam = McqExam::updateOrCreate(
                    [
                        'tenant_id' => $sahodaya->id,
                        'code' => 'MCQ-2026-27',
                    ],
                    [
                        'academic_year_id' => $academicYear->id,
                        'title' => 'Sahodaya Talent Search MCQ 2026-27',
                        'exam_type' => 'assessment',
                        'delivery_mode' => 'offline',
                        'conductor_level' => 'sahodaya',
                        'scheduled_at' => '2026-09-05 10:00:00',
                        'registration_opens_at' => '2026-07-28 00:00:00',
                        'registration_closes_at' => '2026-08-20 23:59:59',
                        'duration_minutes' => 60,
                        'total_questions' => 50,
                        'pass_mark' => 20,
                        'status' => 'published',
                        'fee_type' => 'flat',
                        'fee_amount' => 100,
                        'school_discount_amount' => 10,
                        'payment_deadline' => '2026-08-20',
                    ],
                );

                $training = TrainingProgram::query()
                    ->where('tenant_id', $sahodaya->id)
                    ->where('title', 'Teacher Training 2026-27')
                    ->first()
                    ?? TrainingProgram::query()
                        ->where('tenant_id', $sahodaya->id)
                        ->where('title', 'test')
                        ->first()
                    ?? new TrainingProgram(['tenant_id' => $sahodaya->id]);

                $training->fill([
                    'academic_year_id' => $academicYear->id,
                    'title' => 'Teacher Training 2026-27',
                    'code' => 'TRN-2026-27',
                    'description' => 'Demo teacher-training workflow for scoped administrator testing.',
                    'venue' => 'Malappuram Sahodaya Training Centre',
                    'start_date' => '2026-08-22',
                    'end_date' => '2026-08-22',
                    'conductor_level' => 'sahodaya',
                    'registration_open' => '2026-07-28',
                    'registration_close' => '2026-08-15',
                    'max_participants' => 100,
                    'allow_teacher_self_registration' => true,
                    'allow_school_nomination' => true,
                    'require_verified_teachers' => true,
                    'allow_school_attendance' => true,
                    'status' => 'published',
                    'fee_type' => 'none',
                    'fee_amount' => 0,
                    'min_attendance_percent' => 75,
                    'certificate_type' => 'participation',
                ]);
                $training->save();

                $provisioner = app(TenantUserProvisioner::class);
                $fullEventPermissions = TenantUserCatalog::defaultPermissionsForRole('event_admin');

                $englishAdmin = $this->upsertUser(
                    $provisioner,
                    $sahodaya->id,
                    'English Fest Admin',
                    'english.admin@malappuram.test',
                    'english.admin',
                    ['event_admin'],
                    $fullEventPermissions,
                );
                $this->syncEventAssignments(
                    $englishAdmin,
                    collect([$englishFest])->merge($regions)->pluck('id')->all(),
                );

                foreach ($regions->take(2)->values() as $index => $regionEvent) {
                    $number = $index + 1;
                    $regionAdmin = $this->upsertUser(
                        $provisioner,
                        $sahodaya->id,
                        "English Fest Region {$number} Admin",
                        "english.region{$number}@malappuram.test",
                        "english.region{$number}",
                        ['event_admin'],
                        $fullEventPermissions,
                    );
                    $this->syncEventAssignments($regionAdmin, [$regionEvent->id]);
                }

                $examAdmin = $this->upsertUser(
                    $provisioner,
                    $sahodaya->id,
                    'MCQ Exam Controller',
                    'mcq.admin@malappuram.test',
                    'mcq.admin',
                    ['exam_controller'],
                    [],
                );
                McqExamStaff::updateOrCreate(
                    ['exam_id' => $exam->id, 'user_id' => $examAdmin->id],
                    ['role' => 'controller'],
                );

                $feeService = app(McqSchoolFeeService::class);
                McqRegistration::where('exam_id', $exam->id)
                    ->distinct()
                    ->pluck('school_id')
                    ->each(function (string $schoolId) use ($exam, $feeService): void {
                        if ($school = Tenant::find($schoolId)) {
                            $feeService->syncForSchool($exam->fresh(), $school);
                        }
                    });

                $this->upsertUser(
                    $provisioner,
                    $sahodaya->id,
                    'Teacher Training Admin',
                    'training.admin@malappuram.test',
                    'training.admin',
                    ['training_admin'],
                    ['training.view', 'training.manage'],
                );

                $this->command?->info(sprintf(
                    'Scoped demo admins ready: English Fest event + 2 regions, MCQ exam %d, training program %d.',
                    $exam->id,
                    $training->id,
                ));
            });
        });
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    private function upsertUser(
        TenantUserProvisioner $provisioner,
        string $tenantId,
        string $name,
        string $email,
        string $username,
        array $roles,
        array $permissions,
    ): User {
        $existing = User::query()
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->first();

        $result = $provisioner->upsert(
            tenantId: $tenantId,
            roles: $roles,
            permissions: $permissions,
            name: $name,
            email: $email,
            password: self::PASSWORD,
            userId: $existing?->id,
            username: $username,
        );

        $result['user']->forceFill(['must_change_password' => false])->save();

        return $result['user']->fresh();
    }

    /** @param  list<int>  $eventIds */
    private function syncEventAssignments(User $user, array $eventIds): void
    {
        FestEventStaff::query()
            ->where('user_id', $user->id)
            ->where('duty', 'event_admin')
            ->whereNotIn('event_id', $eventIds ?: [0])
            ->delete();

        foreach ($eventIds as $eventId) {
            FestEventStaff::firstOrCreate([
                'event_id' => $eventId,
                'user_id' => $user->id,
                'duty' => 'event_admin',
            ]);
        }
    }
}
