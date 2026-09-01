<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Subscribe;

use Psr\Log\LoggerInterface;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Domain\Exception\NewsletterSubscriberNotFoundException;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Domain\Shared\UnsubscribeToken;

final class SubscribeUseCase implements SubscribeUseCaseInterface
{
    public function __construct(
        private readonly NewsletterRepositoryInterface $newsletterRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly UsersConfig $usersConfig,
    ) {
    }

    /** @throws DatabaseException */
    public function execute(SubscribeRequest $request): SubscribeResponse
    {
        try {
            $subscriber = $this->newsletterRepository->findByEmail($request->email);
        } catch (NewsletterSubscriberNotFoundException) {
            $subscriber = NewsletterSubscriber::create(
                NewsletterSubscriberId::generate(),
                $request->email,
                $request->name,
                $request->source,
            );
            try {
                $this->newsletterRepository->save($subscriber);
            } catch (DatabaseException $e) {
                throw new DatabaseException('Failed to save subscriber.', 0, $e);
            }

            $this->sendConfirmationEmail($subscriber);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load subscriber.', 0, $e);
        }

        return new SubscribeResponse($subscriber);
    }

    /**
     * A brand-new subscription only — re-subscribing an already-subscribed email returns the
     * existing row without saving (see the catch above) and must not re-send this email.
     */
    private function sendConfirmationEmail(NewsletterSubscriber $subscriber): void
    {
        try {
            $unsubscribeToken = UnsubscribeToken::generate($subscriber->email, $this->usersConfig->cancellationSecret);
            $this->mailer->sendSubscriptionConfirmedEmail($subscriber->email, $subscriber->name, $unsubscribeToken);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send subscription confirmation email', [
                'subscriberId' => $subscriber->id->toString(),
                'email'        => $subscriber->email,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
