<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipationPolicy;
use App\Models\FestQualification;
use App\Models\Student;
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

        $categoryError = $this->validateCategory($student, $event, $item);
        if ($categoryError) {
            $errors[] = "{$student->name}: {$categoryError}";
        }

        $qualError = $this->validateSchoolQualification($student, $event);
        if ($qualError) {
            $errors[] = "{$student->name}: {$qualError}";
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
    public function annotateStudents(Collection $students, FestEvent $event): Collection
    {
        return $students->map(function (Student $student) use ($event) {
            $classNum = FestStudentClassResolver::classNumberFromStudent($student);

            return array_merge($student->only(['id', 'name', 'reg_no', 'gender', 'dob', 'academic_year_id']), [
                'class_name' => $student->schoolClass?->name,
                'class_number' => $classNum,
                'kalolsav_class_group' => FestStudentClassResolver::kalolsavClassGroupForStudent($student),
                'kids_fest_band' => FestStudentClassResolver::kidsFestBandForStudent($student),
                'sports_age_group' => FestSportsAgeGroup::primaryAgeGroupForStudent($student, $event),
                'eligible_sports_groups' => FestSportsAgeGroup::eligibleAgeGroupsForStudent($student, $event),
                'sports_age_on_cutoff' => FestSportsAgeGroup::ageOnCutoff($student, $event),
                'eligible_kalolsav' => FestStudentClassResolver::isKalolsavEligible($student),
                'eligible_kids_fest' => FestStudentClassResolver::isKidsFestEligible($student),
            ]);
        });
    }

    /** @param  Collection<int, array<string, mixed>>  $annotatedStudents */
    public function filterEligibleForItem(Collection $annotatedStudents, FestEvent $event, FestEventItem $item): Collection
    {
        return $annotatedStudents->filter(function (array $row) use ($event, $item) {
            $student = new Student($row);
            $student->id = $row['id'];
            $student->setRelation('schoolClass', (object) ['name' => $row['class_name'] ?? '']);

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
            'kalolsavam' => $this->validateKalolsav($student, $item),
            'kids_fest'  => $this->validateKidsFest($student, $item),
            'sports'     => $this->validateSports($student, $event, $item),
            default      => null,
        };
    }

    private function validateKalolsav(Student $student, FestEventItem $item): ?string
    {
        $classNum = FestStudentClassResolver::classNumberFromStudent($student);

        if ($classNum !== null && $classNum <= 2) {
            return 'Classes 1–2 cannot register for Kalotsav — use Kids Fest.';
        }

        $studentGroup = FestStudentClassResolver::kalolsavClassGroupForStudent($student);
        if ($studentGroup === null) {
            return 'class could not be mapped to a Kalotsav category (Classes 3–12 only).';
        }

        $itemGroup = $item->class_group ?? 'open';
        if ($itemGroup === 'open') {
            return null;
        }

        if ($studentGroup !== $itemGroup) {
            $labels = FestClassGroupScheme::labels(null, $item->event);
            $expected = $labels[$itemGroup] ?? strtoupper($itemGroup);
            $actual = $labels[$studentGroup] ?? strtoupper($studentGroup);

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

    private function validateSchoolQualification(Student $student, FestEvent $event): ?string
    {
        if (! $event->id) {
            return null;
        }

        if ($event->event_type !== 'sports' || ($event->level_round ?? 'sahodaya') !== 'sahodaya') {
            return null;
        }

        $policy = FestParticipationPolicy::where('event_id', $event->id)
            ->whereNull('class_group')
            ->first();

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
