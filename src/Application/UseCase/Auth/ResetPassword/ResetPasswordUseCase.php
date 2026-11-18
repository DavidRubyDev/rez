<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\ResetPassword;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\PasswordResetRepositoryInterface;
use Rez\Application\Port\RefreshTokenRepositoryInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Service\EmailVerificationService;
use Rez\Application\Service\PasswordPolicy;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Exception\PasswordTooLongException;
use Rez\Domain\Exception\PasswordTooShortException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\HashedPassword;

final class ResetPasswordUseCase implements ResetPasswordUseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordResetRepositoryInterface $passwordResetRepository,
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly EmailVerificationService $emailVerification,
    ) {
    }

    /**
     * @throws PasswordTooShortException
     * @throws PasswordTooLongException
     * @throws InvalidTokenException
     * @throws \Rez\Domain\Exception\UserNotFoundException
     * @throws DatabaseException
     */
    public function execute(ResetPasswordRequest $request): ResetPasswordResponse
    {
        $tokenHash = hash('sha256', $request->token);

        try {
            $record = $this->passwordResetRepository->findByTokenHash($tokenHash);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load password reset token.', 0, $e);
        }

        if ($record['expires_at'] < Utc::now()) {
            throw new InvalidTokenException('Token has expired');
        }

        $this->passwordPolicy->assertAllows($request->newPassword);

        try {
            $user = $this->userRepository->findByEmail($record['email']);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load user.', 0, $e);
        }

        $updated = $user->withPassword(HashedPassword::fromPlainText($request->newPassword));

        try {
            $this->userRepository->save($updated);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save user.', 0, $e);
        }

        // The reset link reached this inbox, which proves ownership just as a verification
        // link would — and is the only way an admin-created user ever proves it.
        $this->emailVerification->markVerified($updated);

        try {
            $this->passwordResetRepository->deleteByEmail($record['email']);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to delete password reset token.', 0, $e);
        }

        // A reset is how someone locked out — or worried their session was stolen — takes
        // the account back, so every existing login must stop working with it.
        try {
            $this->refreshTokenRepository->revokeAllForUser($user->id, Utc::now());
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to revoke refresh tokens.', 0, $e);
        }

        return new ResetPasswordResponse(true);
    }
}
