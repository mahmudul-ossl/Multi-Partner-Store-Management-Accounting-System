<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

class SelfApprovalException extends AuthorizationException
{
    public function __construct(string $message = 'You cannot approve your own transaction.')
    {
        parent::__construct($message);
    }
}
