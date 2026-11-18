<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

final class PasswordTooShortException extends DomainException
{
    public function __construct(public readonly int $minLength)
    {
        parent::__construct("Password must be at least {$minLength} characters long.");
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::PasswordTooShort;
    }

    public function errorParams(): array
    {
        return ['min_length' => (string) $this->minLength];
    }
}
