<?php

namespace App\Support\Concerns;

/**
 * Shared "Classes 5, 6 & 7" style suffix formatting for models that carry a `classes`
 * array cast (class numbers 1-12). Used by both FestEventClassGroup (legacy, per-event
 * custom categories) and FestClassCategorySchemeGroup (named, Sahodaya-wide schemes).
 */
trait HasClassesSuffix
{
    public function classesSuffix(): string
    {
        $order = ['pre-kg' => -3, 'pre_kg' => -3, 'lkg' => -2, 'ukg' => -1];

        $classes = collect($this->classes ?? [])
            ->map(fn ($c) => trim((string) $c))
            ->filter(fn ($c) => $c !== '')
            ->sort(function ($a, $b) use ($order) {
                $valA = $order[strtolower($a)] ?? (is_numeric($a) ? (int) $a : 99);
                $valB = $order[strtolower($b)] ?? (is_numeric($b) ? (int) $b : 99);
                return $valA <=> $valB;
            })
            ->values();

        if ($classes->isEmpty()) {
            return '';
        }

        if ($classes->count() === 1) {
            $first = $classes->first();
            $prefix = is_numeric($first) ? 'Class ' : 'Classes ';
            return ' — ' . $prefix . $first;
        }

        $last = $classes->pop();

        return ' — Classes ' . $classes->implode(', ') . ' & ' . $last;
    }
}
