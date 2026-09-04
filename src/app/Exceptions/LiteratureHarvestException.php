<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class LiteratureHarvestException extends RuntimeException
{
    public function __construct(string $message, public bool $retryable = false, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
