<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\RequestPasswordReset;

use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\PasswordResetRepositoryInterface;
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\Shared\Utc;

final class RequestPasswordResetUseCase implements RequestPasswordResetUseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordResetRepositoryInterface $passwordResetRepository,
        private readonly TokenGeneratorInterface $tokenGenerator,
        private readonly MailerInterface $mailer,
        private readonly UsersConfig $config,
    ) {
    }

    /**
     * @throws DatabaseException
     */
    public function execute(RequestPasswordResetRequest $request): RequestPasswordResetResponse
    {
        try {
            $this->userRepository->findByEmail($request->email);
        } catch (UserNotFoundException) {
            return new RequestPasswordResetResponse(true);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load user.', 0, $e);
        }

        $token     = $this->tokenGenerator->generate();
        $tokenHash = hash('sha256', $token);
        $expiresAt = Utc::now()->modify("+{$this->config->passwordResetTtlMinutes} minutes");

        try {
            $this->passwordResetRepository->save($request->email, $tokenHash, $expiresAt);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save password reset token.', 0, $e);
        }

        $resetUrl = rtrim($request->resetBaseUrl, '/') . '?token=' . $token;
        $this->mailer->sendPasswordReset($request->email, $resetUrl);

        return new RequestPasswordResetResponse(true);
    }
}
