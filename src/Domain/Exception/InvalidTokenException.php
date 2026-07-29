<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

class InvalidTokenException extends DomainException
{
    public function __construct(?string $reason = null)
    {
        parent::__construct($reason === null ? 'Invalid token.' : "Invalid token: {$reason}");
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::InvalidToken;
    }
}
