<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Broadcast;

use Psr\Log\LoggerInterface;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Domain\Shared\UnsubscribeToken;

final class BroadcastUseCase implements BroadcastUseCaseInterface
{
    public function __construct(
        private readonly NewsletterRepositoryInterface $newsletterRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly UsersConfig $usersConfig,
    ) {
    }

    /** @throws DatabaseException */
    public function execute(BroadcastRequest $request): BroadcastResponse
    {
        try {
            $subscribers = $this->newsletterRepository->findAll();
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load newsletter subscribers.', 0, $e);
        }
        $sent = 0;

        foreach ($subscribers as $subscriber) {
            try {
                $unsubscribeToken = UnsubscribeToken::generate($subscriber->email, $this->usersConfig->cancellationSecret);
                $this->mailer->sendNewClassNotification(
                    $subscriber->email,
                    $request->resourceName,
                    $request->resourceDate,
                    $unsubscribeToken,
                );
                $sent++;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send broadcast email to subscriber', [
                    'subscriberId' => $subscriber->id->toString(),
                    'email'        => $subscriber->email,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        return new BroadcastResponse($sent);
    }
}
