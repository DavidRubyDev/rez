<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

final class InvalidCredentialsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Invalid credentials.');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::InvalidCredentials;
    }
}
