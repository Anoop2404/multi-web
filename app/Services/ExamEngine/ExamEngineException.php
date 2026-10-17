<?php

namespace App\Services\ExamEngine;

use RuntimeException;

/** The exam engine is unreachable, not configured, or rejected a request. */
class ExamEngineException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null, public readonly ?string $errorCode = null)
    {
        parent::__construct($message);
    }
}
