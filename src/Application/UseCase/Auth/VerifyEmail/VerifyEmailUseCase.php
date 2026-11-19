<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\VerifyEmail;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailVerificationRepositoryInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Service\AuthTokenIssuer;
use Rez\Application\Service\EmailVerificationService;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Shared\Utc;

final class VerifyEmailUseCase implements VerifyEmailUseCaseInterface
{
    public function __construct(
        private readonly EmailVerificationRepositoryInterface $verificationRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly EmailVerificationService $emailVerification,
        private readonly AuthTokenIssuer $tokenIssuer,
    ) {
    }

    /**
     * Following the link proves the visitor reads that inbox — the same proof a password
     * reset link is trusted with — so it signs them in rather than sending them to log in.
     *
     * @throws InvalidTokenException
     * @throws \Rez\Domain\Exception\UserNotFoundException
     * @throws DatabaseException
     */
    public function execute(VerifyEmailRequest $request): VerifyEmailResponse
    {
        try {
            $record = $this->verificationRepository->findByTokenHash(hash('sha256', $request->token));
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load email verification token.', 0, $e);
        }

        if ($record['expires_at'] < Utc::now()) {
            throw new InvalidTokenException('Token has expired');
        }

        try {
            $user = $this->userRepository->findById($record['user_id']);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load user.', 0, $e);
        }

        $verified = $this->emailVerification->markVerified($user);

        return new VerifyEmailResponse($verified, $this->tokenIssuer->issue($verified));
    }
}
