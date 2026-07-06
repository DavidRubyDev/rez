<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

final class EmailAlreadyRegisteredException extends DomainException
{
    public function __construct(string $email)
    {
        parent::__construct("Email '{$email}' is already registered.");
    }
}
