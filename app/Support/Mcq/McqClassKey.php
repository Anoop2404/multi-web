<?php

namespace App\Support\Mcq;

final class McqClassKey
{
    public static function normalize(?string $name): string
    {
        $name = strtoupper(trim(preg_replace('/^CLASS\s*/i', '', trim((string) $name))));
        return (string) (['I'=>1,'II'=>2,'III'=>3,'IV'=>4,'V'=>5,'VI'=>6,'VII'=>7,'VIII'=>8,'IX'=>9,'X'=>10,'XI'=>11,'XII'=>12][$name] ?? $name);
    }
}
