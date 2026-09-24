<?php

namespace App\Exceptions;

use RuntimeException;

class LastOwnerException extends RuntimeException
{
    public static function make(): self
    {
        return new self('The last owner of a business cannot be removed, deactivated or demoted.');
    }
}
