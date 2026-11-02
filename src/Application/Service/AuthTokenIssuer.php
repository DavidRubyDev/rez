<?php

declare(strict_types=1);

namespace Rez\Application\Service;

use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\RefreshTokenRepositoryInterface;
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Domain\Auth\RefreshToken;
use Rez\Domain\Auth\RefreshTokenFamilyId;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\User;

/**
 * The single place a short-lived access token and its long-lived refresh token are minted
 * together — login, registration and refresh all end here, so they cannot drift apart.
 */
final class AuthTokenIssuer
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly TokenGeneratorInterface $tokenGenerator,
        private readonly UsersConfig $config,
    ) {
    }

    /**
     * Omit $familyId to start a new login; pass it when rotating so the successor stays
     * revocable together with every token it descends from.
     *
     * @throws DatabaseException
     */
    public function issue(User $user, ?RefreshTokenFamilyId $familyId = null): AuthTokens
    {
        $now                   = Utc::now();
        $rawRefreshToken       = $this->tokenGenerator->generate();
        $refreshTokenExpiresAt = $now->modify("+{$this->config->refreshTokenTtlSeconds} seconds");

        try {
            $this->refreshTokenRepository->save(RefreshToken::issue(
                $familyId ?? RefreshTokenFamilyId::generate(),
                $user->id,
                $rawRefreshToken,
                $refreshTokenExpiresAt,
            ));
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save refresh token.', 0, $e);
        }

        return new AuthTokens(
            $this->jwtService->generate($user->id, $user->role),
            $now->modify("+{$this->config->accessTokenTtlSeconds} seconds"),
            $rawRefreshToken,
            $refreshTokenExpiresAt,
        );
    }
}
