<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

class DuplicateApprovalException extends AuthorizationException
{
    public function __construct(string $message = 'You have already approved this request.')
    {
        parent::__construct($message);
    }
}
