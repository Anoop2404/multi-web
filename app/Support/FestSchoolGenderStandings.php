<?php

namespace App\Support;

use App\Models\FestEvent;

class FestSchoolGenderStandings
{
    public static function categories(FestEvent $event): array
    {
        return array_values(array_filter((array) ($event->rootEvent()->aggregation_config['school_gender_categories'] ?? []), 'is_string'));
    }

    public static function enabled(FestEvent $event, ?string $category = null): bool
    {
        return in_array($category ?? '*', self::categories($event), true);
    }

    public static function requestedGender(FestEvent $event, ?string $category = null): ?string
    {
        $gender = request()->query('gender');
        return self::enabled($event, $category) && in_array($gender, ['male', 'female'], true) ? $gender : null;
    }

    public static function filterMarks($query, string $gender): void
    {
        $query->where(function ($q) use ($gender) {
            $q->whereHas('item', fn ($item) => $item->where('gender', $gender))
                ->orWhere(function ($open) use ($gender) {
                    $open->whereHas('item', fn ($item) => $item->where('participant_type', 'individual')
                        ->where(function ($eligibility) {
                            $eligibility->whereNull('gender')->orWhereNotIn('gender', ['male', 'female']);
                        }))
                        ->whereHas('participant.student', fn ($student) => $student->where('gender', $gender));
                });
        });
    }
}
