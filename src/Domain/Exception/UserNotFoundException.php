<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

final class UserNotFoundException extends DomainException
{
    public function __construct(private readonly string $identifier)
    {
        parent::__construct("User '{$identifier}' not found.");
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::UserNotFound;
    }

    public function errorParams(): array
    {
        return ['identifier' => $this->identifier];
    }
}
