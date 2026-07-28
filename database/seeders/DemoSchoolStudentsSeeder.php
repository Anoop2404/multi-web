<?php

namespace Database\Seeders;

use App\Models\AcademicYearRecord;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Students\SchoolClassProvisioner;
use App\Services\Students\StudentRegistrationNumberGenerator;
use App\Support\AcademicYear;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DemoSchoolStudentsSeeder extends Seeder
{
    private const STUDENTS_PER_CLASS = 50;

    private const TEST_DATA_MARKER = '[DEMO TEST DATA: 50 STUDENTS PER CLASS]';

    public function run(): void
    {
        // The demo database may have schools before the global class templates
        // have been seeded. This seeder is idempotent, so it is safe to ensure
        // those templates before provisioning each school's class list.
        $this->call(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::query()
            ->where('type', 'sahodaya')
            ->where('subdomain', 'malappuram')
            ->first();

        if (! $sahodaya) {
            throw new RuntimeException('The Malappuram Sahodaya demo tenant was not found.');
        }

        $schools = Tenant::query()
            ->where('type', 'school')
            ->where('parent_id', $sahodaya->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($schools->isEmpty()) {
            throw new RuntimeException('No active member schools were found for Malappuram Sahodaya.');
        }

        $sahodaya->run(function () use ($sahodaya, $schools): void {
            $academicYear = AcademicYearRecord::query()->active()->first();

            if (! $academicYear) {
                throw new RuntimeException('No active academic year exists in the Malappuram tenant database.');
            }

            $provisioner = app(SchoolClassProvisioner::class);

            foreach ($schools as $school) {
                $provisioner->ensureForSchool($school);
            }

            $schoolIds = $schools->pluck('id')->all();
            $yearSuffix = AcademicYear::yearSuffix($academicYear->label);
            $registrationBase = StudentRegistrationNumberGenerator::PREFIX.'/'.$yearSuffix.'/';
            $nextRegistrationSequence = $this->highestRegistrationSequence($schoolIds, $registrationBase) + 1;
            $createdBySchool = [];

            DB::transaction(function () use (
                $schools,
                $academicYear,
                $registrationBase,
                &$nextRegistrationSequence,
                &$createdBySchool,
            ): void {
                foreach ($schools as $school) {
                    $classes = SchoolClass::query()
                        ->where('tenant_id', $school->id)
                        ->active()
                        ->orderBy('display_order')
                        ->orderBy('name')
                        ->get();

                    if ($classes->isEmpty()) {
                        throw new RuntimeException("No active classes could be provisioned for {$school->name}.");
                    }

                    $expectedAdmissionNumbers = [];
                    foreach ($classes as $class) {
                        for ($studentNumber = 1; $studentNumber <= self::STUDENTS_PER_CLASS; $studentNumber++) {
                            $expectedAdmissionNumbers[] = $this->admissionNumber(
                                $school,
                                $class,
                                $studentNumber,
                            );
                        }
                    }

                    $existingAdmissionNumbers = Student::withTrashed()
                        ->where('tenant_id', $school->id)
                        ->where('academic_year_id', $academicYear->id)
                        ->whereIn('admission_number', $expectedAdmissionNumbers)
                        ->pluck('admission_number')
                        ->flip();

                    $rows = [];
                    foreach ($classes as $class) {
                        for ($studentNumber = 1; $studentNumber <= self::STUDENTS_PER_CLASS; $studentNumber++) {
                            $admissionNumber = $this->admissionNumber($school, $class, $studentNumber);

                            if ($existingAdmissionNumbers->has($admissionNumber)) {
                                continue;
                            }

                            $registrationNumber = $registrationBase.str_pad(
                                (string) $nextRegistrationSequence++,
                                4,
                                '0',
                                STR_PAD_LEFT,
                            );

                            $rows[] = $this->studentRow(
                                $school,
                                $class,
                                $academicYear,
                                $studentNumber,
                                $admissionNumber,
                                $registrationNumber,
                            );
                        }
                    }

                    foreach (array_chunk($rows, 500) as $chunk) {
                        Student::query()->insert($chunk);
                    }

                    $createdBySchool[$school->name] = count($rows);
                }
            });

            foreach ($createdBySchool as $schoolName => $created) {
                $this->command?->line("  {$schoolName}: created {$created} student(s).");
            }

            $this->command?->info(sprintf(
                'Demo student data ready: %d schools, %d students per active class.',
                $schools->count(),
                self::STUDENTS_PER_CLASS,
            ));
        });
    }

    /**
     * @param  list<string>  $schoolIds
     */
    private function highestRegistrationSequence(array $schoolIds, string $registrationBase): int
    {
        return Student::withTrashed()
            ->whereIn('tenant_id', $schoolIds)
            ->where('reg_no', 'like', $registrationBase.'%')
            ->pluck('reg_no')
            ->map(function (?string $registrationNumber) use ($registrationBase): int {
                if (! $registrationNumber || ! str_starts_with($registrationNumber, $registrationBase)) {
                    return 0;
                }

                $sequence = substr($registrationNumber, strlen($registrationBase));

                return ctype_digit($sequence) ? (int) $sequence : 0;
            })
            ->max() ?? 0;
    }

    private function admissionNumber(Tenant $school, SchoolClass $class, int $studentNumber): string
    {
        $schoolCode = Str::upper($school->school_prefix ?: Str::substr(Str::slug($school->name, ''), 0, 4));
        $classCode = Str::upper(Str::slug($class->name, ''));

        return sprintf('TEST-%s-%s-%03d', $schoolCode, $classCode, $studentNumber);
    }

    /**
     * @return array<string, mixed>
     */
    private function studentRow(
        Tenant $school,
        SchoolClass $class,
        AcademicYearRecord $academicYear,
        int $studentNumber,
        string $admissionNumber,
        string $registrationNumber,
    ): array {
        $schoolCode = Str::upper($school->school_prefix ?: 'SCH');
        $gender = $studentNumber % 2 === 0 ? 'female' : 'male';
        $now = now();

        return [
            'tenant_id'        => $school->id,
            'academic_year_id' => $academicYear->id,
            'school_class_id'  => $class->id,
            'admission_number' => $admissionNumber,
            'reg_no'           => $registrationNumber,
            'roll_number'      => (string) $studentNumber,
            'name'             => sprintf(
                'Test %s Class %s Student %03d',
                $schoolCode,
                $class->name,
                $studentNumber,
            ),
            'dob'              => $this->dateOfBirth($academicYear, $class, $studentNumber),
            'gender'           => $gender,
            'blood_group'      => ['A+', 'B+', 'O+', 'AB+'][($studentNumber - 1) % 4],
            'parent_name'      => sprintf('Test Parent %s %03d', $schoolCode, $studentNumber),
            'parent_phone'     => sprintf('900%07d', ($class->id * 100) + $studentNumber),
            'parent_email'     => sprintf(
                'parent.%s.%s.%03d@example.test',
                Str::lower($schoolCode),
                Str::lower(Str::slug($class->name, '')),
                $studentNumber,
            ),
            'address'          => 'Demo test record — not a real student.',
            'admission_date'   => $academicYear->start_date?->toDateString(),
            'status'           => 'active',
            'notes'            => self::TEST_DATA_MARKER,
            'verified_at'      => $now,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    private function dateOfBirth(
        AcademicYearRecord $academicYear,
        SchoolClass $class,
        int $studentNumber,
    ): string {
        $academicStartYear = (int) Str::before($academicYear->label, '-');
        $classAge = match (Str::upper($class->name)) {
            'LKG' => 4,
            'UKG' => 5,
            default => ((int) $class->name) + 5,
        };

        $month = (($studentNumber - 1) % 5) + 1;
        $day = (($studentNumber - 1) % 27) + 1;

        return sprintf('%04d-%02d-%02d', $academicStartYear - $classAge, $month, $day);
    }
}
