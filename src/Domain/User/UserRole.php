<?php

declare(strict_types=1);

namespace Rez\Domain\User;

enum UserRole
{
    case Customer;
    case Admin;
}
