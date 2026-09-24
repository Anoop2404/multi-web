<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Student reg_no is issued as STU/{year}/{sequence} (StudentRegistrationNumberGenerator,
 * e.g. STU/27/1111). Winner sheets need something shorter a reader can tell students apart
 * with at a glance — the trailing sequence alone (1111), not the full STU/27/ prefix.
 */
class StudentIdFormat
{
    /**
     * A team's combined reg_no is a ", "-joined list (see FestItemResultsService), so each
     * member is shortened individually and rejoined the same way. A reg_no that isn't in the
     * STU/{year}/{sequence} shape (legacy data, teacher reg_no, test fixtures) has no "/" to
     * split on and is returned unchanged rather than mangled.
     */
    public static function shortId(?string $regNo): ?string
    {
        if (blank($regNo)) {
            return null;
        }

        return collect(explode(', ', $regNo))
            ->map(fn (string $one) => Str::afterLast(trim($one), '/'))
            ->implode(', ');
    }
}
