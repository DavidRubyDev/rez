<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Broadcast;

use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\NewsletterRepositoryInterface;

final class BroadcastUseCase implements BroadcastUseCaseInterface
{
    public function __construct(
        private readonly NewsletterRepositoryInterface $newsletterRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function execute(BroadcastRequest $request): BroadcastResponse
    {
        $subscribers = $this->newsletterRepository->findAll();
        $sent        = 0;

        foreach ($subscribers as $subscriber) {
            $this->mailer->sendNewClassNotification($subscriber->email, $request->className, $request->classDate);
            $sent++;
        }

        return new BroadcastResponse($sent);
    }
}
