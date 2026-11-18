<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

final class PasswordTooLongException extends DomainException
{
    public function __construct(public readonly int $maxLength)
    {
        parent::__construct("Password must be at most {$maxLength} characters long.");
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::PasswordTooLong;
    }

    public function errorParams(): array
    {
        return ['max_length' => (string) $this->maxLength];
    }
}
