<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Refresh;

use Psr\Log\LoggerInterface;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\RefreshTokenRepositoryInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Service\AuthTokenIssuer;
use Rez\Domain\Auth\RefreshToken;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\Shared\Utc;

final class RefreshUseCase implements RefreshUseCaseInterface
{
    public function __construct(
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly AuthTokenIssuer $tokenIssuer,
        private readonly UsersConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws InvalidTokenException
     * @throws DatabaseException
     */
    public function execute(RefreshRequest $request): RefreshResponse
    {
        try {
            $presented = $this->refreshTokenRepository->findByTokenHash(RefreshToken::hashOf($request->refreshToken));
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load refresh token.', 0, $e);
        }

        $now = Utc::now();

        if ($presented->isRevoked()) {
            throw new InvalidTokenException('Refresh token has been revoked.');
        }

        if ($presented->isExpiredAt($now)) {
            throw new InvalidTokenException('Refresh token has expired.');
        }

        if ($presented->isUsed()) {
            // Two tabs (or two bundles on one page) racing to refresh the same token is
            // normal and gets a grace window. Outside it, a used token coming back means
            // someone else holds a copy — end that whole login rather than guess who.
            if (!$presented->wasUsedWithin($this->config->refreshTokenReuseGraceSeconds, $now)) {
                $this->revokeReplayedFamily($presented, $now);
            }
        } else {
            try {
                $this->refreshTokenRepository->save($presented->markUsed($now));
            } catch (DatabaseException $e) {
                throw new DatabaseException('Failed to mark refresh token used.', 0, $e);
            }
        }

        try {
            $user = $this->userRepository->findById($presented->userId);
        } catch (UserNotFoundException) {
            throw new InvalidTokenException('Refresh token belongs to a user that no longer exists.');
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load user.', 0, $e);
        }

        return new RefreshResponse($user, $this->tokenIssuer->issue($user, $presented->familyId));
    }

    /**
     * @throws InvalidTokenException
     * @throws DatabaseException
     */
    private function revokeReplayedFamily(RefreshToken $presented, \DateTimeImmutable $now): never
    {
        $this->logger->warning('Refresh token reuse detected; revoking its family', [
            'userId'   => $presented->userId->toString(),
            'familyId' => $presented->familyId->toString(),
        ]);

        try {
            $this->refreshTokenRepository->revokeFamily($presented->familyId, $now);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to revoke refresh token family.', 0, $e);
        }

        throw new InvalidTokenException('Refresh token has already been used.');
    }
}
