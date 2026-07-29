<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

final class EmailAlreadyRegisteredException extends DomainException
{
    public function __construct(private readonly string $email)
    {
        parent::__construct("Email '{$email}' is already registered.");
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::EmailAlreadyRegistered;
    }

    public function errorParams(): array
    {
        return ['email' => $this->email];
    }
}
