<?php

namespace App\Services\Migration;

use App\Models\ClassCategory;
use App\Models\MembershipFeeSlab;
use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\SchoolYearStudentCount;
use App\Models\SchoolYearSubmission;
use App\Models\Tenant;
use App\Services\Membership\FeeReceiptService;
use App\Services\Membership\MembershipFeeCalculator;
use App\Services\Membership\SchoolMembershipNumberGenerator;
use App\Support\SchoolApplicationForm;
use App\Support\TenantStorage;
use App\Support\TenancyDatabase;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KannurLegacyMembershipImporter
{
    private const LEGACY_TRANSACTION_PREFIX = 'LEGACY-';

    /** @var array<string, string> */
    private const CLASS_TO_CATEGORY = [
        'KG' => 'PRE',
        'LKG' => 'PRE',
        'UKG' => 'PRE',
        'PRIMARY' => 'PRY',
        'MIDDLE' => 'UP',
        'SECONDARY' => 'SEC',
        'HIGHER SECONDARY' => 'SrSEC',
        'SR.SECONDARY' => 'SrSEC',
        'SR SECONDARY' => 'SrSEC',
    ];

    public function __construct(
        private LegacySqlInsertParser $parser,
        private MembershipFeeCalculator $feeCalculator,
        private SchoolMembershipNumberGenerator $membershipNumberGenerator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function import(
        Tenant $sahodaya,
        string $sqlPath,
        bool $dryRun = false,
        ?string $legacyUploadsPath = null,
        ?Command $output = null,
        ?string $storageDisk = null,
        bool $proofsOnly = false,
    ): array {
        if (! is_readable($sqlPath)) {
            throw new \InvalidArgumentException("Legacy SQL dump not found: {$sqlPath}");
        }

        $sql = file_get_contents($sqlPath);
        $legacy = $this->loadLegacyData($sql);

        if ($proofsOnly) {
            return $this->syncLegacyProofs(
                $sahodaya,
                $legacy,
                $dryRun,
                $legacyUploadsPath,
                $storageDisk,
                $output,
            );
        }
        $academicYear = $this->normalizeAcademicYear($legacy['academic_years'][0]['year_name'] ?? '2026-2027');
        $flatFee = $this->legacyFlatFee($legacy['fee_structure']);
        $slabs = $this->combinedSlabs($legacy['student_wise_fee'], $flatFee, $academicYear);
        $linkedUserIds = $this->linkedSchoolUserIds($legacy['users']);
        $schoolsByUserId = $this->indexLegacySchools($legacy['schools'], $linkedUserIds);
        $importableUserIds = array_fill_keys(array_keys($schoolsByUserId), true);
        $strengthByUserId = $this->indexStrength($legacy['student_strength'], $importableUserIds);
        $paymentsByUserId = $this->indexPayments($legacy['payments'], $importableUserIds);
        $newSchools = $this->indexNewSchools($sahodaya);

        $stats = [
            'academic_year'        => $academicYear,
            'flat_fee'             => $flatFee,
            'slab_count'           => count($slabs),
            'legacy_school_users'  => count($linkedUserIds),
            'legacy_schools'       => count($schoolsByUserId),
            'legacy_payments'      => count($paymentsByUserId),
            'skipped_no_user'      => $this->countSkippedSchools($legacy['schools'], $linkedUserIds),
            'matched_schools'      => 0,
            'unmatched_schools'    => [],
            'registrations_created'=> 0,
            'registrations_updated'=> 0,
            'payments_imported'    => 0,
            'payments_skipped'     => 0,
            'proofs_copied'        => 0,
            'proofs_missing'       => 0,
            'dues_pending'         => 0,
            'completed'            => 0,
        ];

        $this->line(
            $output,
            "Academic year: {$academicYear}; flat fee ₹{$flatFee}; "
            .count($slabs).' combined slab(s); '
            .count($schoolsByUserId).' school(s) with linked portal user(s).',
        );

        if ($dryRun) {
            $this->previewMatches($schoolsByUserId, $paymentsByUserId, $strengthByUserId, $newSchools, $stats, $output);

            return $stats;
        }

        TenancyDatabase::withTenantDatabase($sahodaya, function () use (
            $sahodaya,
            $academicYear,
            $slabs,
            $schoolsByUserId,
            $strengthByUserId,
            $paymentsByUserId,
            $newSchools,
            $legacyUploadsPath,
            &$stats,
            $output,
            $storageDisk,
        ) {
            $this->configureMembership($sahodaya, $academicYear, $slabs, $output);

            $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->firstOrFail();
            $categoryMap = $this->classCategoryMap();

            foreach ($schoolsByUserId as $userId => $legacySchool) {
                $newSchool = $this->matchNewSchool($legacySchool, $newSchools);
                if (! $newSchool) {
                    $stats['unmatched_schools'][] = [
                        'legacy_user_id'   => $userId,
                        'legacy_name'      => $legacySchool['school_name'] ?? '',
                        'affiliation_no'   => $legacySchool['affiliation_no'] ?? '',
                        'email'            => $legacySchool['email'] ?? '',
                        'has_payment'      => isset($paymentsByUserId[$userId]),
                        'has_strength'     => isset($strengthByUserId[$userId]),
                    ];

                    continue;
                }

                $stats['matched_schools']++;
                $payment = $paymentsByUserId[$userId] ?? null;
                $strengthRows = $strengthByUserId[$userId] ?? [];

                DB::transaction(function () use (
                    $newSchool,
                    $legacySchool,
                    $userId,
                    $academicYear,
                    $profile,
                    $categoryMap,
                    $strengthRows,
                    $payment,
                    $legacyUploadsPath,
                    $storageDisk,
                    &$stats,
                ) {
                    $registrationExists = Registration::query()
                        ->where('school_id', $newSchool->id)
                        ->where('academic_year', $academicYear)
                        ->exists();

                    $submission = $this->ensureSubmission($newSchool, $academicYear, $profile, $strengthRows, $categoryMap);
                    $registration = $this->ensureRegistration(
                        $newSchool,
                        $academicYear,
                        $profile,
                        $submission,
                        $payment,
                    );

                    if ($registrationExists) {
                        $stats['registrations_updated']++;
                    } else {
                        $stats['registrations_created']++;
                    }

                    if ($payment) {
                        $imported = $this->importPayment(
                            $newSchool,
                            $registration,
                            $academicYear,
                            $payment,
                            $legacyUploadsPath,
                            $storageDisk,
                            $stats,
                        );

                        if ($imported) {
                            $stats['payments_imported']++;
                        } else {
                            $stats['payments_skipped']++;
                        }
                    }

                    $registration = $registration->fresh(['submission.counts']);
                    $feeAmount = (float) ($registration->membership_fee_amount ?? 0);
                    $paidAmount = (float) MembershipPayment::query()
                        ->where('school_id', $newSchool->id)
                        ->where('academic_year', $academicYear)
                        ->whereIn('status', ['verified', 'submitted'])
                        ->sum('amount');

                    $status = $this->resolveRegistrationStatus($registration, $payment, $feeAmount, $paidAmount);
                    if ($registration->registration_status !== $status) {
                        $registration->update(['registration_status' => $status]);
                    }

                    if ($status === 'completed') {
                        $stats['completed']++;
                        $this->ensureSchoolApproved($newSchool);
                        $this->ensureMembershipNumber($registration);
                    } elseif (in_array($status, ['payment_pending', 'payment_submitted', 'payment_rejected'], true)) {
                        $stats['dues_pending']++;
                    }
                });
            }
        });

        if ($stats['unmatched_schools'] !== []) {
            $this->line($output, 'Unmatched legacy schools: '.count($stats['unmatched_schools']));
        }

        return $stats;
    }

    /**
     * @return array<string, list<array<string, string|null>>>
     */
    private function loadLegacyData(string $sql): array
    {
        return [
            'academic_years'     => $this->parser->parseTable($sql, 'tb_academic_years'),
            'fee_structure'      => $this->parser->parseTable($sql, 'tb_fee_structure'),
            'student_wise_fee'   => $this->parser->parseTable($sql, 'tb_student_wise_fee'),
            'schools'            => $this->parser->parseTable($sql, 'tb_schools'),
            'users'              => $this->parser->parseTable($sql, 'tb_users'),
            'payments'           => $this->parser->parseTable($sql, 'tb_payments'),
            'student_strength'   => $this->parser->parseTable($sql, 'tb_student_strength'),
        ];
    }

    /**
     * Active school portal users from the legacy system (role = school).
     *
     * @param  list<array<string, string|null>>  $users
     * @return array<string, true>
     */
    public function linkedSchoolUserIds(array $users): array
    {
        $linked = [];

        foreach ($users as $user) {
            if (($user['is_deleted'] ?? 'N') === 'Y') {
                continue;
            }

            if ((string) ($user['role'] ?? '') !== '2') {
                continue;
            }

            $userId = (string) ($user['user_id'] ?? '');
            if ($userId !== '') {
                $linked[$userId] = true;
            }
        }

        return $linked;
    }

    /**
     * @param  list<array<string, string|null>>  $schools
     * @param  array<string, true>  $linkedUserIds
     */
    public function countSkippedSchools(array $schools, array $linkedUserIds): int
    {
        $skipped = 0;

        foreach ($schools as $school) {
            if (($school['is_deleted'] ?? 'N') === 'Y') {
                continue;
            }

            $userId = (string) ($school['user_id'] ?? '');
            if ($userId === '' || ! isset($linkedUserIds[$userId])) {
                $skipped++;
            }
        }

        return $skipped;
    }

    /**
     * @param  list<array<string, string|null>>  $feeStructure
     */
    public function legacyFlatFee(array $feeStructure): float
    {
        foreach ($feeStructure as $row) {
            $type = strtolower((string) ($row['fee_type'] ?? ''));
            if (in_array($type, ['annual registration', 'annual_membership'], true)) {
                return (float) ($row['amount'] ?? 0);
            }
        }

        return 5000.0;
    }

    /**
     * @param  list<array<string, string|null>>  $studentWiseFee
     * @return list<array{min_students:int,max_students:?int,amount:float}>
     */
    public function combinedSlabs(array $studentWiseFee, float $flatFee, string $academicYear): array
    {
        $rows = collect($studentWiseFee);
        $yearSpecific = $rows->where('year_id', '1');
        $source = $yearSpecific->isNotEmpty() ? $yearSpecific : $rows->whereNull('year_id');

        return $source
            ->unique(fn (array $row) => ($row['min_strength'] ?? '').'-'.($row['max_strength'] ?? ''))
            ->sortBy(fn (array $row) => (int) ($row['min_strength'] ?? 0))
            ->values()
            ->map(fn (array $row) => [
                'academic_year' => $academicYear,
                'min_students'  => (int) ($row['min_strength'] ?? 0),
                'max_students'  => isset($row['max_strength']) ? (int) $row['max_strength'] : null,
                'amount'        => round($flatFee + (float) ($row['fees'] ?? 0), 2),
            ])->all();
    }

    public function normalizeAcademicYear(string $legacyYear): string
    {
        if (preg_match('/^(\d{4})-(\d{4})$/', trim($legacyYear), $matches)) {
            return $matches[1].'-'.substr($matches[2], -2);
        }

        return trim($legacyYear);
    }

    /**
     * @param  list<array<string, string|null>>  $schools
     * @param  array<string, true>  $linkedUserIds
     * @return array<string, array<string, string|null>>
     */
    private function indexLegacySchools(array $schools, array $linkedUserIds): array
    {
        $indexed = [];
        foreach ($schools as $school) {
            if (($school['is_deleted'] ?? 'N') === 'Y') {
                continue;
            }

            $userId = (string) ($school['user_id'] ?? '');
            if ($userId === '' || ! isset($linkedUserIds[$userId])) {
                continue;
            }

            $indexed[$userId] = $school;
        }

        return $indexed;
    }

    /**
     * @param  list<array<string, string|null>>  $strength
     * @param  array<string, true>  $linkedUserIds
     * @return array<string, list<array<string, string|null>>>
     */
    private function indexStrength(array $strength, array $linkedUserIds): array
    {
        $indexed = [];
        foreach ($strength as $row) {
            if (($row['year_id'] ?? null) !== '1' || ($row['is_deleted'] ?? 'N') === 'Y') {
                continue;
            }

            $userId = (string) ($row['school_id'] ?? '');
            if ($userId === '' || ! isset($linkedUserIds[$userId])) {
                continue;
            }

            $indexed[$userId][] = $row;
        }

        return $indexed;
    }

    /**
     * @param  list<array<string, string|null>>  $payments
     * @param  array<string, true>  $linkedUserIds
     * @return array<string, array<string, string|null>>
     */
    private function indexPayments(array $payments, array $linkedUserIds): array
    {
        $indexed = [];
        foreach ($payments as $payment) {
            $userId = (string) ($payment['school_id'] ?? '');
            if ($userId === '' || ! isset($linkedUserIds[$userId])) {
                continue;
            }

            $indexed[$userId] = $payment;
        }

        return $indexed;
    }

    /**
     * @return array{by_affiliation: Collection<string, Tenant>, by_email: Collection<string, Tenant>}
     */
    private function indexNewSchools(Tenant $sahodaya): array
    {
        $schools = Tenant::query()
            ->where('parent_id', $sahodaya->id)
            ->where('type', 'school')
            ->get();

        $byAffiliation = collect();
        $byEmail = collect();

        foreach ($schools as $school) {
            $payload = is_array($school->application_payload) ? $school->application_payload : [];
            $affiliation = SchoolApplicationForm::normalizeAffiliation(
                $payload['cbse_affiliation'] ?? $payload['affiliation_number'] ?? null,
            );
            if ($affiliation) {
                $byAffiliation->put($affiliation, $school);
            }

            $email = strtolower(trim((string) ($payload['school_email'] ?? $school->email ?? '')));
            if ($email !== '') {
                $byEmail->put($email, $school);
            }
        }

        return [
            'by_affiliation' => $byAffiliation,
            'by_email' => $byEmail,
        ];
    }

    /**
     * @param  array<string, string|null>  $legacySchool
     * @param  array{by_affiliation: Collection<string, Tenant>, by_email: Collection<string, Tenant>}  $newSchools
     */
    private function matchNewSchool(array $legacySchool, array $newSchools): ?Tenant
    {
        $affiliation = SchoolApplicationForm::normalizeAffiliation($legacySchool['affiliation_no'] ?? null);
        if ($affiliation && $newSchools['by_affiliation']->has($affiliation)) {
            return $newSchools['by_affiliation']->get($affiliation);
        }

        $email = strtolower(trim((string) ($legacySchool['email'] ?? '')));
        if ($email !== '' && $newSchools['by_email']->has($email)) {
            return $newSchools['by_email']->get($email);
        }

        return null;
    }

    /**
     * @param  list<array{min_students:int,max_students:?int,amount:float,academic_year:string}>  $slabs
     */
    private function configureMembership(Tenant $sahodaya, string $academicYear, array $slabs, ?Command $output): void
    {
        $profile = SahodayaProfile::firstOrCreate(
            ['tenant_id' => $sahodaya->id],
            ['membership_fee_type' => 'variable_by_student_count'],
        );

        $profile->update([
            'membership_fee_type'       => 'variable_by_student_count',
            'fixed_membership_fee_amount' => null,
            'student_data_mode'         => 'counts_only',
            'active_academic_year'      => $academicYear,
        ]);

        foreach ($slabs as $slab) {
            MembershipFeeSlab::updateOrCreate(
                [
                    'sahodaya_id'   => $sahodaya->id,
                    'academic_year' => $academicYear,
                    'min_students'  => $slab['min_students'],
                    'max_students'  => $slab['max_students'],
                ],
                ['amount' => $slab['amount']],
            );
        }

        $this->line($output, 'Configured membership profile and fee slabs.');
    }

    /**
     * @return array<string, ClassCategory>
     */
    private function classCategoryMap(): array
    {
        return ClassCategory::global()
            ->active()
            ->get()
            ->keyBy(fn (ClassCategory $category) => strtoupper($category->code))
            ->all();
    }

    /**
     * @param  list<array<string, string|null>>  $strengthRows
     * @param  array<string, ClassCategory>  $categoryMap
     */
    private function ensureSubmission(
        Tenant $school,
        string $academicYear,
        SahodayaProfile $profile,
        array $strengthRows,
        array $categoryMap,
    ): SchoolYearSubmission {
        $submission = SchoolYearSubmission::firstOrCreate(
            [
                'school_id'     => $school->id,
                'academic_year' => $academicYear,
            ],
            [
                'full_records_status' => 'not_applicable',
                'counts_status'       => $strengthRows === [] ? 'pending' : 'approved',
                'teacher_status'      => $profile->teacher_registration_enabled ? 'pending' : 'not_applicable',
            ],
        );

        if ($strengthRows === []) {
            return $submission;
        }

        $aggregated = $this->aggregateStrengthByCategory($strengthRows, $categoryMap);
        foreach ($aggregated as $categoryId => $counts) {
            SchoolYearStudentCount::updateOrCreate(
                [
                    'school_year_submission_id' => $submission->id,
                    'class_category_id'         => $categoryId,
                ],
                [
                    'male_count'   => $counts['male'],
                    'female_count' => $counts['female'],
                    'total_count'  => $counts['total'],
                ],
            );
        }

        if ($submission->counts_status !== 'approved') {
            $submission->update(['counts_status' => 'approved']);
        }

        return $submission->fresh('counts');
    }

    /**
     * @param  list<array<string, string|null>>  $strengthRows
     * @param  array<string, ClassCategory>  $categoryMap
     * @return array<int, array{male:int,female:int,total:int}>
     */
    public function aggregateStrengthByCategory(array $strengthRows, array $categoryMap): array
    {
        $aggregated = [];

        foreach ($strengthRows as $row) {
            $code = $this->legacyClassToCategoryCode((string) ($row['class'] ?? ''));
            $category = $categoryMap[$code] ?? null;
            if (! $category) {
                continue;
            }

            $male = (int) ($row['male'] ?? 0);
            $female = (int) ($row['female'] ?? 0);
            $total = (int) ($row['strength'] ?? ($male + $female));

            if (! isset($aggregated[$category->id])) {
                $aggregated[$category->id] = ['male' => 0, 'female' => 0, 'total' => 0];
            }

            $aggregated[$category->id]['male'] += $male;
            $aggregated[$category->id]['female'] += $female;
            $aggregated[$category->id]['total'] += $total;
        }

        return $aggregated;
    }

    public function legacyClassToCategoryCode(string $classLabel): string
    {
        $normalized = strtoupper(trim($classLabel));

        if (isset(self::CLASS_TO_CATEGORY[$normalized])) {
            return self::CLASS_TO_CATEGORY[$normalized];
        }

        if (is_numeric($normalized)) {
            $classNumber = (int) $normalized;
            if ($classNumber <= 0) {
                return 'PRE';
            }
            if ($classNumber <= 5) {
                return 'PRY';
            }
            if ($classNumber <= 8) {
                return 'UP';
            }
            if ($classNumber <= 10) {
                return 'SEC';
            }

            return 'SrSEC';
        }

        if (str_contains($normalized, 'HIGHER')) {
            return 'SrSEC';
        }
        if (str_contains($normalized, 'PRIMARY')) {
            return 'PRY';
        }
        if (str_contains($normalized, 'MIDDLE')) {
            return 'UP';
        }
        if (str_contains($normalized, 'SECONDARY')) {
            return 'SEC';
        }

        return 'PRE';
    }

    private function ensureRegistration(
        Tenant $school,
        string $academicYear,
        SahodayaProfile $profile,
        SchoolYearSubmission $submission,
        ?array $payment = null,
    ): Registration {
        $registration = Registration::firstOrCreate(
            [
                'school_id'     => $school->id,
                'academic_year' => $academicYear,
            ],
            [
                'registration_status'       => 'data_pending',
                'school_year_submission_id' => $submission->id,
            ],
        );

        if (! $registration->school_year_submission_id) {
            $registration->update(['school_year_submission_id' => $submission->id]);
        }

        $this->feeCalculator->calculateAndApply(
            $registration->fresh(['school.parent']),
            $profile,
            $submission->fresh('counts') ?? $submission,
        );

        $registration = $registration->fresh();
        $paidAmount = (float) ($payment['amount'] ?? 0);
        if ((float) ($registration->membership_fee_amount ?? 0) <= 0 && $paidAmount > 0) {
            $registration->update(['membership_fee_amount' => $paidAmount]);
        }

        return $registration->fresh();
    }

    /**
     * @param  array<string, string|null>  $payment
     */
    private function importPayment(
        Tenant $school,
        Registration $registration,
        string $academicYear,
        array $payment,
        ?string $legacyUploadsPath,
        ?string $storageDisk = null,
        ?array &$stats = null,
    ): bool {
        $legacyPaymentId = (string) ($payment['payment_id'] ?? '');
        $transactionRef = self::LEGACY_TRANSACTION_PREFIX.$legacyPaymentId
            .($payment['payment_details'] ? ': '.$payment['payment_details'] : '');

        $existing = MembershipPayment::query()
            ->where('school_id', $school->id)
            ->where('academic_year', $academicYear)
            ->where('transaction_ref', 'like', self::LEGACY_TRANSACTION_PREFIX.$legacyPaymentId.'%')
            ->first();

        if ($existing) {
            return false;
        }

        $proof = $this->storeLegacyProof($school, $payment, $legacyUploadsPath, $storageDisk);
        if ($stats !== null) {
            if ($proof['copied']) {
                $stats['proofs_copied']++;
            } else {
                $stats['proofs_missing']++;
            }
        }
        $status = ($payment['is_verified'] ?? 'N') === 'Y' ? 'verified' : 'submitted';
        $verifiedAt = $status === 'verified'
            ? $this->parseLegacyDate($payment['created_date'] ?? $payment['payment_date'] ?? null)
            : null;

        $membershipPayment = MembershipPayment::create([
            'school_id'        => $school->id,
            'academic_year'    => $academicYear,
            'registration_id'  => $registration->id,
            'amount'           => (float) ($payment['amount'] ?? 0),
            'payment_proof_path' => $proof['path'],
            'payment_method'   => $this->normalizePaymentMethod($payment),
            'transaction_ref'  => $transactionRef,
            'status'           => $status,
            'verified_at'      => $verifiedAt,
        ]);

        app(FeeReceiptService::class)->syncFromMembershipPayment($membershipPayment->fresh());

        return true;
    }

    /**
     * @param  array<string, list<array<string, string|null>>>  $legacy
     * @return array<string, mixed>
     */
    private function syncLegacyProofs(
        Tenant $sahodaya,
        array $legacy,
        bool $dryRun,
        ?string $legacyUploadsPath,
        ?string $storageDisk,
        ?Command $output,
    ): array {
        if (! $legacyUploadsPath || ! is_dir($legacyUploadsPath)) {
            throw new \InvalidArgumentException('Legacy uploads directory is required for --proofs-only.');
        }

        $academicYear = $this->normalizeAcademicYear($legacy['academic_years'][0]['year_name'] ?? '2026-2027');
        $linkedUserIds = $this->linkedSchoolUserIds($legacy['users']);
        $schoolsByUserId = $this->indexLegacySchools($legacy['schools'], $linkedUserIds);
        $importableUserIds = array_fill_keys(array_keys($schoolsByUserId), true);
        $paymentsByUserId = $this->indexPayments($legacy['payments'], $importableUserIds);
        $newSchools = $this->indexNewSchools($sahodaya);

        $stats = [
            'mode'            => 'proofs_only',
            'academic_year'   => $academicYear,
            'payments_found'  => 0,
            'proofs_copied'   => 0,
            'proofs_missing'  => 0,
            'proofs_skipped'  => 0,
            'unmatched'       => [],
        ];

        $this->line($output, "Syncing legacy payment proofs from: {$legacyUploadsPath}");

        TenancyDatabase::withTenantDatabase($sahodaya, function () use (
            $academicYear,
            $schoolsByUserId,
            $paymentsByUserId,
            $newSchools,
            $legacyUploadsPath,
            $storageDisk,
            $dryRun,
            &$stats,
            $output,
        ) {
            foreach ($schoolsByUserId as $userId => $legacySchool) {
                $payment = $paymentsByUserId[$userId] ?? null;
                if (! $payment) {
                    continue;
                }

                $newSchool = $this->matchNewSchool($legacySchool, $newSchools);
                if (! $newSchool) {
                    $stats['unmatched'][] = $legacySchool['school_name'] ?? $userId;
                    continue;
                }

                $legacyPaymentId = (string) ($payment['payment_id'] ?? '');
                $membershipPayment = MembershipPayment::query()
                    ->where('school_id', $newSchool->id)
                    ->where('academic_year', $academicYear)
                    ->where('transaction_ref', 'like', self::LEGACY_TRANSACTION_PREFIX.$legacyPaymentId.'%')
                    ->first();

                if (! $membershipPayment) {
                    $stats['proofs_skipped']++;
                    continue;
                }

                $stats['payments_found']++;
                $proof = $this->storeLegacyProof($newSchool, $payment, $legacyUploadsPath, $storageDisk, $dryRun);

                if ($proof['copied']) {
                    $stats['proofs_copied']++;
                } else {
                    $stats['proofs_missing']++;
                }

                if (! $dryRun && $proof['copied'] && $membershipPayment->payment_proof_path !== $proof['path']) {
                    $membershipPayment->update(['payment_proof_path' => $proof['path']]);
                    app(FeeReceiptService::class)->syncFromMembershipPayment($membershipPayment->fresh());
                }
            }
        });

        return $stats;
    }

    /**
     * @param  array<string, string|null>  $payment
     * @return array{path: string, copied: bool}
     */
    private function storeLegacyProof(
        Tenant $school,
        array $payment,
        ?string $legacyUploadsPath,
        ?string $storageDisk = null,
        bool $dryRun = false,
    ): array {
        $filename = trim((string) ($payment['receipt_file'] ?? ''));
        if ($filename === '') {
            return ['path' => 'legacy-import/kannur/missing-proof.txt', 'copied' => false];
        }

        $storagePath = "payments/{$school->id}/legacy/{$filename}";
        $source = $legacyUploadsPath ? $this->findLegacyProofSource($legacyUploadsPath, $filename) : null;

        if (! $source) {
            return ['path' => "legacy-import/kannur/{$filename}", 'copied' => false];
        }

        if (! $dryRun) {
            $disk = $storageDisk ?? TenantStorage::uploadDisk();
            TenantStorage::put($storagePath, (string) file_get_contents($source), $disk);
        }

        return ['path' => $storagePath, 'copied' => true];
    }

    private function findLegacyProofSource(string $root, string $filename): ?string
    {
        $direct = rtrim($root, '/').'/'.$filename;
        if (is_readable($direct)) {
            return $direct;
        }

        if (! is_dir($root)) {
            return null;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $payment
     */
    private function normalizePaymentMethod(array $payment): ?string
    {
        $parts = array_filter([
            trim((string) ($payment['payment_method'] ?? '')),
            trim((string) ($payment['payment_type'] ?? '')),
        ]);

        return $parts === [] ? null : Str::limit(implode(' / ', $parts), 120, '');
    }

    private function parseLegacyDate(?string $value): ?Carbon
    {
        if (! $value || str_starts_with($value, '0000-00-00')) {
            return now();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return now();
        }
    }

    /**
     * @param  array<string, string|null>|null  $payment
     */
    private function resolveRegistrationStatus(
        Registration $registration,
        ?array $payment,
        float $feeAmount,
        float $paidAmount,
    ): string {
        if ($payment && ($payment['is_verified'] ?? 'N') === 'Y' && $paidAmount + 0.009 >= $feeAmount && $feeAmount > 0) {
            return 'completed';
        }

        if ($payment && ($payment['is_verified'] ?? 'N') === 'N') {
            return 'payment_submitted';
        }

        if ($feeAmount > 0) {
            return 'payment_pending';
        }

        return $registration->registration_status;
    }

    private function ensureSchoolApproved(Tenant $school): void
    {
        if ($school->membership_status === 'approved') {
            return;
        }

        $school->update([
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);
    }

    private function ensureMembershipNumber(Registration $registration): void
    {
        if ($registration->reg_no) {
            return;
        }

        $school = $registration->school ?? Tenant::find($registration->school_id);
        if (! $school) {
            return;
        }

        try {
            $registration->update([
                'reg_no' => $this->membershipNumberGenerator->generate($school, $registration->academic_year),
            ]);
        } catch (\Throwable) {
            // Prefix may not be configured yet; registration can still be completed.
        }
    }

    /**
     * @param  array<string, array<string, string|null>>  $schoolsByUserId
     * @param  array<string, array<string, string|null>>  $paymentsByUserId
     * @param  array<string, list<array<string, string|null>>>  $strengthByUserId
     * @param  array{by_affiliation: Collection<string, Tenant>, by_email: Collection<string, Tenant>}  $newSchools
     * @param  array<string, mixed>  $stats
     */
    private function previewMatches(
        array $schoolsByUserId,
        array $paymentsByUserId,
        array $strengthByUserId,
        array $newSchools,
        array &$stats,
        ?Command $output,
    ): void {
        foreach ($schoolsByUserId as $userId => $legacySchool) {
            $match = $this->matchNewSchool($legacySchool, $newSchools);
            if ($match) {
                $stats['matched_schools']++;
                $this->line(
                    $output,
                    "✓ {$legacySchool['school_name']} → {$match->name}"
                    .(isset($paymentsByUserId[$userId]) ? ' [payment]' : '')
                    .(isset($strengthByUserId[$userId]) ? ' [strength]' : ''),
                );
            } else {
                $stats['unmatched_schools'][] = [
                    'legacy_user_id' => $userId,
                    'legacy_name'    => $legacySchool['school_name'] ?? '',
                    'affiliation_no' => $legacySchool['affiliation_no'] ?? '',
                    'email'          => $legacySchool['email'] ?? '',
                    'has_payment'    => isset($paymentsByUserId[$userId]),
                    'has_strength'   => isset($strengthByUserId[$userId]),
                ];
            }
        }
    }

    private function line(?Command $output, string $message): void
    {
        if ($output) {
            $output->line($message);
        }
    }
}
