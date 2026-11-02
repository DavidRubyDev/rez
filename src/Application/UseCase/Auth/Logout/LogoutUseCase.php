<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Logout;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\RefreshTokenRepositoryInterface;
use Rez\Domain\Auth\RefreshToken;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Shared\Utc;

final class LogoutUseCase implements LogoutUseCaseInterface
{
    public function __construct(
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
    ) {
    }

    /**
     * Logging out with a token that is unknown or already dead still succeeds — the caller
     * wants to end up logged out, and that is already true.
     *
     * @throws DatabaseException
     */
    public function execute(LogoutRequest $request): LogoutResponse
    {
        try {
            $token = $this->refreshTokenRepository->findByTokenHash(RefreshToken::hashOf($request->refreshToken));
        } catch (InvalidTokenException) {
            return new LogoutResponse();
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load refresh token.', 0, $e);
        }

        try {
            $this->refreshTokenRepository->revokeFamily($token->familyId, Utc::now());
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to revoke refresh token family.', 0, $e);
        }

        return new LogoutResponse();
    }
}
