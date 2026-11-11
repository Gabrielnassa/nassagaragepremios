<?php

namespace App\Exceptions;

use RuntimeException;

class CheckoutException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'geral', public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
