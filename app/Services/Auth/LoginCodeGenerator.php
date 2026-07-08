<?php

namespace App\Services\Auth;

use App\Models\Teacher;
use App\Models\Tenant;
use App\Support\AcademicYear;
use Illuminate\Support\Facades\DB;

class LoginCodeGenerator
{
    public const TEACHER_PREFIX = 'T';

    private const SEQUENCE_PAD = 4;

    /**
     * Assign a T/YY/0001 login code (per-Sahodaya, per-academic-year) to a teacher.
     * The value is stored on both login_code (portal username) and reg_no.
     */
    public function assignTeacher(Teacher $teacher): string
    {
        if (filled($teacher->login_code) && $this->isFormatted($teacher->login_code)) {
            if ($teacher->reg_no !== $teacher->login_code) {
                $teacher->forceFill(['reg_no' => $teacher->login_code])->save();
            }

            return $teacher->login_code;
        }

        $base = $this->baseForTeacher($teacher);

        $code = DB::transaction(fn () => $this->nextCode($base));

        $teacher->forceFill(['login_code' => $code, 'reg_no' => $code])->save();

        return $code;
    }

    public function isFormatted(?string $value): bool
    {
        return $value !== null && (bool) preg_match('/^T\/\d{2}\/\d{3,}$/i', trim($value));
    }

    public function baseForTeacher(Teacher $teacher): string
    {
        $sahodayaId = Tenant::query()->whereKey($teacher->tenant_id)->value('parent_id');
        $yearSuffix = AcademicYear::yearSuffix(AcademicYear::forSahodaya($sahodayaId));

        return self::TEACHER_PREFIX.'/'.$yearSuffix.'/';
    }

    public function nextCode(string $base): string
    {
        $max = Teacher::query()
            ->whereNotNull('login_code')
            ->where('login_code', 'like', $base.'%')
            ->lockForUpdate()
            ->pluck('login_code')
            ->map(function (string $code) use ($base) {
                $tail = substr($code, strlen($base));

                return ctype_digit($tail) ? (int) $tail : 0;
            })
            ->max();

        $next = max(1, ($max ?? 0) + 1);

        return $base.str_pad((string) $next, self::SEQUENCE_PAD, '0', STR_PAD_LEFT);
    }
}
