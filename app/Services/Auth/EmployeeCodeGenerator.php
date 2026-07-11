<?php

namespace App\Services\Auth;

use App\Models\Teacher;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class EmployeeCodeGenerator
{
    public const PREFIX = 'EMP';

    private const SEQUENCE_PAD = 5;

    /**
     * Assign a permanent EMP/{school_prefix}/00001 employee code to a teacher.
     * Distinct from LoginCodeGenerator's Teacher ID (T/YY/0001): scoped per-school
     * (not per-Sahodaya-per-year) and never re-derived once set, so it stays fixed
     * for the teacher's whole tenure regardless of academic-year rollovers.
     */
    public function assign(Teacher $teacher): string
    {
        if (filled($teacher->employee_code) && $this->isFormatted($teacher->employee_code)) {
            return $teacher->employee_code;
        }

        $base = $this->baseForTeacher($teacher);

        $code = DB::transaction(fn () => $this->nextCode($base));

        $teacher->forceFill(['employee_code' => $code])->save();

        return $code;
    }

    public function isFormatted(?string $value): bool
    {
        return $value !== null && (bool) preg_match('/^EMP\/[A-Za-z0-9]+\/\d{4,}$/i', trim($value));
    }

    public function baseForTeacher(Teacher $teacher): string
    {
        $prefix = Tenant::query()->whereKey($teacher->tenant_id)->value('school_prefix') ?: 'SCH';

        return self::PREFIX.'/'.$prefix.'/';
    }

    public function nextCode(string $base): string
    {
        $max = Teacher::query()
            ->withTrashed()
            ->whereNotNull('employee_code')
            ->where('employee_code', 'like', $base.'%')
            ->lockForUpdate()
            ->pluck('employee_code')
            ->map(function (string $code) use ($base) {
                $tail = substr($code, strlen($base));

                return ctype_digit($tail) ? (int) $tail : 0;
            })
            ->max();

        $next = max(1, ($max ?? 0) + 1);

        return $base.str_pad((string) $next, self::SEQUENCE_PAD, '0', STR_PAD_LEFT);
    }
}
