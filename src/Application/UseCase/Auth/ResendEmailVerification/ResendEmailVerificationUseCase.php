<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\ResendEmailVerification;

use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailVerificationRepositoryInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Service\EmailVerificationService;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\User;

/**
 * Always answers the same way — whether the email is unknown, already verified, was sent a
 * link moments ago or gets a new one — so it can't be used to find out who has an account.
 */
final class ResendEmailVerificationUseCase implements ResendEmailVerificationUseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly EmailVerificationRepositoryInterface $verificationRepository,
        private readonly EmailVerificationService $emailVerification,
        private readonly UsersConfig $config,
    ) {
    }

    /**
     * @throws DatabaseException
     */
    public function execute(ResendEmailVerificationRequest $request): ResendEmailVerificationResponse
    {
        try {
            $user = $this->userRepository->findByEmail($request->email);
        } catch (UserNotFoundException) {
            return new ResendEmailVerificationResponse(true);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load user.', 0, $e);
        }

        if (!$user->isEmailVerified() && !$this->wasSentALinkRecently($user)) {
            $this->emailVerification->sendLink($user, $request->verificationBaseUrl);
        }

        return new ResendEmailVerificationResponse(true);
    }

    /**
     * Without this anyone could fill a stranger's inbox by pressing "resend" in a loop.
     *
     * @throws DatabaseException
     */
    private function wasSentALinkRecently(User $user): bool
    {
        try {
            $issuedAt = $this->verificationRepository->findIssuedAtForUser($user->id);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load email verification token.', 0, $e);
        }

        $cooldownStart = Utc::now()->modify("-{$this->config->emailVerificationResendCooldownSeconds} seconds");

        return $issuedAt !== null && $issuedAt > $cooldownStart;
    }
}
