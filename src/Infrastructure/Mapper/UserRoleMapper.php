<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Mapper;

use Rez\Domain\User\UserRole;

final class UserRoleMapper
{
    public function toString(UserRole $role): string
    {
        return match ($role) {
            UserRole::Customer => 'customer',
            UserRole::Admin    => 'admin',
        };
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function fromString(string $value): UserRole
    {
        return match ($value) {
            'customer' => UserRole::Customer,
            'admin'    => UserRole::Admin,
            default    => throw new \InvalidArgumentException(sprintf('Unknown UserRole value: "%s".', $value)),
        };
    }
}
