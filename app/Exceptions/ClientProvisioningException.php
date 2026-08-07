<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ClientProvisioningException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $compensationComplete,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            0,
            $previous
        );
    }
}
