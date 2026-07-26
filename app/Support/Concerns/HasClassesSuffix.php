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
        $classes = collect($this->classes ?? [])->map(fn ($c) => (int) $c)->filter()->sort()->values();

        if ($classes->isEmpty()) {
            return '';
        }

        if ($classes->count() === 1) {
            return ' — Class '.$classes->first();
        }

        $last = $classes->pop();

        return ' — Classes '.$classes->implode(', ').' & '.$last;
    }
}
