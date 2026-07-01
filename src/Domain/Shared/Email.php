<?php

declare(strict_types=1);

namespace Rez\Domain\Shared;

final class Email
{
    public static function isValid(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
