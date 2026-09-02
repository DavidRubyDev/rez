<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Unsubscribe;

use Psr\Log\LoggerInterface;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Exception\NewsletterSubscriberNotFoundException;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Shared\UnsubscribeToken;

final class UnsubscribeUseCase implements UnsubscribeUseCaseInterface
{
    public function __construct(
        private readonly NewsletterRepositoryInterface $newsletterRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly UsersConfig $usersConfig,
    ) {
    }

    /**
     * @throws DatabaseException
     * @throws InvalidTokenException
     */
    public function execute(UnsubscribeRequest $request): UnsubscribeResponse
    {
        try {
            $subscriber = $this->newsletterRepository->findByEmail($request->email);
        } catch (NewsletterSubscriberNotFoundException) {
            return new UnsubscribeResponse(removed: false);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load subscriber.', 0, $e);
        }

        if ($request->token !== null) {
            $this->assertValidToken($request->email, $request->token);
        }

        try {
            $this->newsletterRepository->delete($subscriber->id);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to delete subscriber.', 0, $e);
        }

        $this->sendConfirmationEmail($subscriber);

        return new UnsubscribeResponse(removed: true);
    }

    /** @throws InvalidTokenException */
    private function assertValidToken(string $email, string $token): void
    {
        if (!UnsubscribeToken::fromString($token)->verify($email, $this->usersConfig->cancellationSecret)) {
            throw new InvalidTokenException();
        }
    }

    private function sendConfirmationEmail(NewsletterSubscriber $subscriber): void
    {
        try {
            $this->mailer->sendUnsubscribeConfirmedEmail($subscriber->email, $subscriber->name);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send unsubscribe confirmation email', [
                'subscriberId' => $subscriber->id->toString(),
                'email'        => $subscriber->email,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
