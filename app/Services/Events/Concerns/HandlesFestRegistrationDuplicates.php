<?php

namespace App\Services\Events\Concerns;

use Illuminate\Database\QueryException;

trait HandlesFestRegistrationDuplicates
{
    protected function isFestRegistrationDuplicate(QueryException $e): bool
    {
        $message = $e->getMessage();

        return ($e->errorInfo[0] ?? null) === '23505'
            || str_contains($message, 'fest_reg_active_unique')
            || str_contains($message, 'Unique violation');
    }

    protected function abortOnFestRegistrationDuplicate(QueryException $e): void
    {
        if ($this->isFestRegistrationDuplicate($e)) {
            abort(422, 'Your school already has an entry for this item.');
        }
    }
}
