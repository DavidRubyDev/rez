<?php

declare(strict_types=1);

namespace Rez\Application\Service;

use Psr\Log\LoggerInterface;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailVerificationRepositoryInterface;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeRequest;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCaseInterface;
use Rez\Domain\Newsletter\SubscriberSource;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\User;

/**
 * Both ends of proving a user owns their email address: sending the link, and recording that
 * they did — whichever way they proved it (the link itself, or a password reset link).
 */
final class EmailVerificationService
{
    public function __construct(
        private readonly EmailVerificationRepositoryInterface $verificationRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TokenGeneratorInterface $tokenGenerator,
        private readonly MailerInterface $mailer,
        private readonly SubscribeUseCaseInterface $subscribeUseCase,
        private readonly UsersConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws DatabaseException
     */
    public function sendLink(User $user, string $verificationBaseUrl): void
    {
        $token = $this->tokenGenerator->generate();
        $now   = Utc::now();

        try {
            $this->verificationRepository->save(
                $user->id,
                hash('sha256', $token),
                $now,
                $now->modify("+{$this->config->emailVerificationTtlMinutes} minutes"),
            );
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save email verification token.', 0, $e);
        }

        // The account already exists by now; failing the request would leave the visitor
        // unable to register again (409) with no link either. They can ask for a new one.
        try {
            $this->mailer->sendEmailVerification($user->email, $user->name, rtrim($verificationBaseUrl, '/') . '?token=' . $token);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send email verification', [
                'userId' => $user->id->toString(),
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * The newsletter opt-in given at registration only takes effect here — subscribing an
     * address nobody has proven they own would let anyone sign up someone else.
     *
     * @throws DatabaseException
     */
    public function markVerified(User $user): User
    {
        if ($user->isEmailVerified()) {
            return $user;
        }

        $verified = $user->withEmailVerifiedAt(Utc::now());

        try {
            $this->userRepository->save($verified);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save user.', 0, $e);
        }

        try {
            $this->verificationRepository->deleteForUser($verified->id);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to delete email verification token.', 0, $e);
        }

        if ($verified->newsletterOptIn) {
            $this->subscribeUseCase->execute(new SubscribeRequest($verified->email, $verified->name, SubscriberSource::Registered));
        }

        return $verified;
    }
}
