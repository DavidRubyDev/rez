<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\ResetPassword;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\PasswordResetRepositoryInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\HashedPassword;

final class ResetPasswordUseCase implements ResetPasswordUseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordResetRepositoryInterface $passwordResetRepository,
    ) {
    }

    /**
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

        try {
            $this->passwordResetRepository->deleteByEmail($record['email']);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to delete password reset token.', 0, $e);
        }

        return new ResetPasswordResponse(true);
    }
}
