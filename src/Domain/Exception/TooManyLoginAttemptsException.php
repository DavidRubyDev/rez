<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

final class TooManyLoginAttemptsException extends DomainException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Too many failed login attempts. Try again later.');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::TooManyLoginAttempts;
    }

    public function errorParams(): array
    {
        return ['retry_after_seconds' => (string) $this->retryAfterSeconds];
    }
}
