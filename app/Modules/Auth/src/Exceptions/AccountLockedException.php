<?php

namespace App\Modules\Auth\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class AccountLockedException extends HttpException
{
    public function __construct(int $retryAfter)
    {
        parent::__construct(
            statusCode: 423,
            message: "Account bloccato per troppi tentativi falliti. Riprova tra {$retryAfter} secondi.",
            headers: ['Retry-After' => $retryAfter],
        );
    }
}
