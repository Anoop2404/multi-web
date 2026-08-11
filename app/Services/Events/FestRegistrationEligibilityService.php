<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestLevelRegistration;
use App\Models\FestParticipationPolicy;
use App\Models\FestQualification;
use App\Models\Student;
use App\Services\Students\StudentVerificationGate;
use App\Support\FestClassGroupScheme;
use App\Support\FestKidsFestBand;
use App\Support\FestSportsAgeGroup;
use App\Support\FestStudentClassResolver;
use Illuminate\Support\Collection;

class FestRegistrationEligibilityService
{
    /** @return list<string> */
    public function validateStudent(Student $student, FestEvent $event, FestEventItem $item): array
    {
        $errors = [];

        if ($event->academic_year_id && $student->academic_year_id
            && (int) $student->academic_year_id !== (int) $event->academic_year_id) {
            $errors[] = "{$student->name} is not enrolled in this event's academic year.";
        }

        $genderError = $this->validateGender($student, $item, $event);
        if ($genderError) {
            $errors[] = "{$student->name}: {$genderError}";
        }

        $verifyError = app(StudentVerificationGate::class)
            ->ineligibilityReason($student, $event);
        if ($verifyError) {
            $errors[] = "{$student->name}: {$verifyError}";
        } else {
            $head = $item->relationLoaded('head')
                ? $item->head
                : ($item->head_id ? $item->head()->first() : null);
            if ($head?->requiresVerifiedStudentsOnly() && ! $student->isVerified()) {
                $errors[] = "{$student->name}: must be Sahodaya-verified to register under {$head->name}.";
            } elseif (! $head?->requiresVerifiedStudentsOnly() && $event->requiresVerifiedStudentsOnly() && ! $student->isVerified()) {
                // Falls back to the event-level policy when the item has no head (Kalotsav
                // items assigned a plain category instead of a head) — see
                // docs/KALOTSAV_ITEM_CATEGORY_REPLACES_HEAD_PLAN.md §5 #3.
                $errors[] = "{$student->name}: must be Sahodaya-verified to register for this event.";
            }
        }

        $categoryError = $this->validateCategory($student, $event, $item);
        if ($categoryError) {
            $errors[] = "{$student->name}: {$categoryError}";
        }

        $qualError = $this->validateSchoolQualification($student, $event);
        if ($qualError) {
            $errors[] = "{$student->name}: {$qualError}";
        }

        foreach (app(FestEligibilityRuleEngine::class)->validateStudent($student, $event, $item) as $ruleError) {
            $errors[] = "{$student->name}: {$ruleError}";
        }

        $area = $item->relationLoaded('area')
            ? $item->area
            : ($item->area_id ? $item->area()->first() : null);
        if ($area?->requiresVerifiedStudentsOnly() && ! $student->isVerified()) {
            $errors[] = "{$student->name}: must be Sahodaya-verified to register under {$area->name}.";
        }

        return $errors;
    }

    /** @param  list<int|string>  $studentIds */
    public function validateStudents(FestEvent $event, FestEventItem $item, array $studentIds): array
    {
        $students = Student::whereIn('id', $studentIds)
            ->with('schoolClass')
            ->get()
            ->keyBy('id');

        $errors = [];
        foreach ($studentIds as $id) {
            $student = $students->get($id);
            if (! $student) {
                $errors[] = 'Invalid student selected.';

                continue;
            }
            $errors = array_merge($errors, $this->validateStudent($student, $event, $item));
        }

        return $errors;
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array<string, mixed>>
     */
    public function annotateStudents(Collection $students, FestEvent $event, ?string $schoolId = null): Collection
    {
        if ($students->isEmpty()) {
            return collect();
        }

        $eventRegByStudent = [];
        if ($schoolId) {
            $studentIds = $students->pluck('id');
            $eventRegByStudent = FestLevelRegistration::query()
                ->whereIn('event_id', $event->reportableEventIds())
                ->where('status', 'active')
                ->whereIn('student_id', $studentIds)
                ->pluck('registration_number', 'student_id')
                ->all();
        }

        $eventType = $event->event_type ?? 'kalolsavam';
        $isSports = $eventType === 'sports';
        $isKalolsav = in_array($eventType, ['kalolsavam', 'custom', 'english_fest', 'science_fest'], true);
        $isKidsFest = $eventType === 'kids_fest';

        return $students->map(function (Student $student) use ($event, $eventRegByStudent, $isSports, $isKalolsav, $isKidsFest) {
            $classNum = FestStudentClassResolver::classNumberFromStudent($student);
            $eventRegNo = $eventRegByStudent[$student->id] ?? null;

            return [
                'id' => $student->id,
                'name' => $student->name,
                'reg_no' => $student->reg_no,
                'admission_number' => $student->admission_number,
                'roll_number' => $student->roll_number,
                'gender' => $student->gender,
                'dob' => $student->dob?->toDateString() ?? $student->dob,
                'academic_year_id' => $student->academic_year_id,
                'class_name' => $student->schoolClass?->name,
                'class_category_id' => $student->schoolClass?->class_category_id,
                'class_number' => $classNum,
                'is_verified' => $student->isVerified(),
                'verified_at' => $student->verified_at?->toIso8601String(),
                'event_registered' => $eventRegNo !== null,
                'event_registration_number' => $eventRegNo,
                'kalolsav_class_group' => $isKalolsav ? FestStudentClassResolver::classGroupForStudent($student, $event) : null,
                'kids_fest_band' => $isKidsFest ? FestStudentClassResolver::kidsFestBandForStudent($student) : null,
                'sports_age_group' => $isSports ? FestSportsAgeGroup::primaryAgeGroupForStudent($student, $event) : null,
                'eligible_sports_groups' => $isSports ? FestSportsAgeGroup::eligibleAgeGroupsForStudent($student, $event) : [],
                'sports_age_on_cutoff' => $isSports ? FestSportsAgeGroup::ageOnCutoff($student, $event) : null,
                'eligible_kalolsav' => $isKalolsav ? FestStudentClassResolver::isKalolsavEligible($student) : false,
                'eligible_kids_fest' => $isKidsFest ? FestStudentClassResolver::isKidsFestEligible($student) : false,
            ];
        });
    }

    /** @param  Collection<int, array<string, mixed>>  $annotatedStudents */
    public function filterEligibleForItem(Collection $annotatedStudents, FestEvent $event, FestEventItem $item): Collection
    {
        return $annotatedStudents->filter(function (array $row) use ($event, $item) {
            $student = new Student([
                'id'               => $row['id'],
                'name'             => $row['name'] ?? '',
                'reg_no'           => $row['reg_no'] ?? null,
                'admission_number' => $row['admission_number'] ?? null,
                'roll_number'      => $row['roll_number'] ?? null,
                'gender'           => $row['gender'] ?? null,
                'dob'              => $row['dob'] ?? null,
                'academic_year_id' => $row['academic_year_id'] ?? null,
                'verified_at'      => $row['verified_at'] ?? null,
            ]);
            $student->id = $row['id'];
            $student->exists = true;

            $schoolClass = new \App\Models\SchoolClass([
                'name' => $row['class_name'] ?? '',
                'class_category_id' => $row['class_category_id'] ?? null,
            ]);
            $schoolClass->exists = true;

            $student->setRelation('schoolClass', $schoolClass);

            return $this->validateStudent($student, $event, $item) === [];
        })->values();
    }

    private function validateGender(Student $student, FestEventItem $item, FestEvent $event): ?string
    {
        $itemGender = strtolower((string) ($item->gender ?? 'open'));
        if (in_array($itemGender, ['open', 'mixed'], true)) {
            return null;
        }

        $studentGender = strtolower((string) ($student->gender ?? ''));

        if ($event->event_type === 'sports') {
            if ($studentGender === '' || $studentGender === 'open') {
                return 'gender must be recorded on the student profile for sports registration.';
            }
        } elseif ($studentGender === '' || $studentGender === 'open') {
            return null;
        }

        if ($studentGender !== $itemGender) {
            $expected = FestSportsAgeGroup::genderLabel($itemGender) ?? $itemGender;

            return "this item is for {$expected} only.";
        }

        return null;
    }

    private function validateCategory(Student $student, FestEvent $event, FestEventItem $item): ?string
    {
        return match ($event->event_type) {
            'kalolsavam' => $this->validateKalolsav($student, $item, $event),
            'kids_fest' => $this->validateKidsFest($student, $item),
            'sports' => $this->validateSports($student, $event, $item),
            'custom' => $this->validateCustomClassGroup($student, $item, $event),
            'english_fest' => $this->validateCustomClassGroup($student, $item, $event),
            'science_fest' => $this->validateCustomClassGroup($student, $item, $event),
            default => null,
        };
    }

    private function validateKalolsav(Student $student, FestEventItem $item, FestEvent $event): ?string
    {
        $classNum = FestStudentClassResolver::classNumberFromStudent($student);

        if ($classNum !== null && $classNum <= 2) {
            return 'Classes 1–2 cannot register for Kalotsav — use Kids Fest.';
        }

        $studentGroup = FestStudentClassResolver::classGroupForStudent($student, $event);
        if ($studentGroup === null) {
            return 'class could not be mapped to a Kalotsav category (Classes 3–12 only).';
        }

        return $this->validateItemClassGroup($studentGroup, $item, $event);
    }

    private function validateCustomClassGroup(Student $student, FestEventItem $item, FestEvent $event): ?string
    {
        $itemGroup = $item->class_group ?? 'open';
        if ($itemGroup === 'open' || $itemGroup === '') {
            return null;
        }

        $studentGroup = FestStudentClassResolver::classGroupForStudent($student, $event);
        if ($studentGroup === null) {
            return FestClassGroupScheme::resolveForEvent($event) === 'cluster'
                ? 'class is not assigned to a membership category.'
                : 'class could not be mapped to a fest category.';
        }

        return $this->validateItemClassGroup($studentGroup, $item, $event);
    }

    private function validateItemClassGroup(string $studentGroup, FestEventItem $item, ?FestEvent $event = null): ?string
    {
        $itemGroup = $item->class_group ?? 'open';
        if ($itemGroup === 'open' || $itemGroup === '') {
            return null;
        }

        $canonicalStudentGroup = FestClassGroupScheme::canonicalKey($studentGroup);
        $canonicalItemGroup = FestClassGroupScheme::canonicalKey($itemGroup);

        if ($canonicalStudentGroup !== $canonicalItemGroup) {
            $labels = FestClassGroupScheme::labels(null, $event ?? $item->event);
            $expected = $labels[$canonicalItemGroup] ?? $labels[$itemGroup] ?? strtoupper($itemGroup);
            $actual = $labels[$canonicalStudentGroup] ?? $labels[$studentGroup] ?? strtoupper($studentGroup);

            return "belongs to {$actual}, but this item is for {$expected}.";
        }

        return null;
    }

    private function validateKidsFest(Student $student, FestEventItem $item): ?string
    {
        $studentBand = FestStudentClassResolver::kidsFestBandForStudent($student);
        if ($studentBand === null) {
            return 'not eligible for Kids Fest (Pre-KG through Class 2 only).';
        }

        $itemBand = $item->kids_band ?? 'open';
        if ($itemBand === 'open' || $itemBand === null || $itemBand === '') {
            return null;
        }

        if (! FestKidsFestBand::isValid($itemBand)) {
            return null;
        }

        if ($studentBand !== $itemBand) {
            $labels = FestKidsFestBand::labels();

            return 'belongs to '.($labels[$studentBand] ?? $studentBand)
                .', but this item is for '.($labels[$itemBand] ?? $itemBand).'.';
        }

        return null;
    }

    private function validateSports(Student $student, FestEvent $event, FestEventItem $item): ?string
    {
        if (! $student->dob) {
            return 'date of birth is required for sports registration.';
        }

        $itemAge = FestSportsAgeGroup::resolveForItem($item->age_group, $item->class_group, 'sports');
        if ($itemAge === null || $itemAge === 'open') {
            return null;
        }

        if (! FestSportsAgeGroup::qualifiesForAgeGroup($student, $itemAge, $event)) {
            $labels = FestSportsAgeGroup::labels($event->tenant_id);
            $cutoff = FestSportsAgeGroup::cutoffDate($event);
            $age = FestSportsAgeGroup::ageOnCutoff($student, $event);
            $underAge = FestSportsAgeGroup::underAge($itemAge, $event->tenant_id);
            $ageHint = $age !== null ? " (age {$age} on {$cutoff->format('d M Y')})" : '';

            return 'must be under '.$underAge.' on the age cutoff date for '
                .($labels[$itemAge] ?? strtoupper($itemAge)).$ageHint.'.';
        }

        return null;
    }

    /**
     * Cached per event per request: this policy row is identical for every
     * student validated against the same event, but validateSchoolQualification()
     * is called once per student from the per-student loop in validateStudent().
     *
     * @var array<int, ?FestParticipationPolicy>
     */
    private static array $qualificationPolicyCache = [];

    private function validateSchoolQualification(Student $student, FestEvent $event): ?string
    {
        if (! $event->id) {
            return null;
        }

        if ($event->event_type !== 'sports' || ($event->level_round ?? 'sahodaya') !== 'sahodaya') {
            return null;
        }

        if (! array_key_exists($event->id, self::$qualificationPolicyCache)) {
            self::$qualificationPolicyCache[$event->id] = FestParticipationPolicy::where('event_id', $event->id)
                ->whereNull('class_group')
                ->first();
        }
        $policy = self::$qualificationPolicyCache[$event->id];

        if (! ($policy?->require_school_qualification ?? false)) {
            return null;
        }

        $qualified = FestQualification::query()
            ->where('next_level_event_id', $event->id)
            ->whereHas('participant', fn ($q) => $q->where('student_id', $student->id))
            ->exists();

        return $qualified ? null : 'must qualify through a linked school-level sports event first.';
    }
}
