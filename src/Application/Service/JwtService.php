<?php

declare(strict_types=1);

namespace Rez\Application\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Rez\Application\Config\UsersConfig;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\User\UserId;
use Rez\Domain\User\UserRole;

final class JwtService
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly UsersConfig $config,
    ) {
    }

    public function generate(UserId $userId, UserRole $role): string
    {
        $now = time();

        return JWT::encode(
            [
                'sub'  => $userId->toString(),
                'role' => $role->name,
                'iat'  => $now,
                'exp'  => $now + $this->config->jwtTtlSeconds,
            ],
            $this->config->jwtSecret,
            self::ALGORITHM,
        );
    }

    /**
     * @return array<string, mixed>
     * @throws InvalidTokenException
     */
    public function validate(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->config->jwtSecret, self::ALGORITHM));
        } catch (\Throwable $e) {
            throw new InvalidTokenException($e->getMessage());
        }

        $payload = [];
        foreach (get_object_vars($decoded) as $key => $value) {
            $payload[(string) $key] = $value;
        }

        return $payload;
    }

    /**
     * @throws InvalidTokenException
     */
    public function extractUserId(string $token): UserId
    {
        $sub = $this->validate($token)['sub'] ?? null;

        if (!is_string($sub)) {
            throw new InvalidTokenException('Missing or invalid subject claim.');
        }

        return UserId::fromString($sub);
    }

    /**
     * @throws InvalidTokenException
     */
    public function extractRole(string $token): UserRole
    {
        $role = $this->validate($token)['role'] ?? null;

        if (!is_string($role)) {
            throw new InvalidTokenException('Missing or invalid role claim.');
        }

        return match ($role) {
            'Customer' => UserRole::Customer,
            'Admin'    => UserRole::Admin,
            default    => throw new InvalidTokenException("Unknown role '{$role}'."),
        };
    }
}
