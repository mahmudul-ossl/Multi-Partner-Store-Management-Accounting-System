<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

class ImmutableDocumentException extends AuthorizationException
{
    public function __construct(string $message = 'Approved records cannot be edited.')
    {
        parent::__construct($message);
    }
}
