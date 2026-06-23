<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Subscribe;

use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Domain\Exception\NewsletterSubscriberNotFoundException;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;

final class SubscribeUseCase implements SubscribeUseCaseInterface
{
    public function __construct(
        private readonly NewsletterRepositoryInterface $newsletterRepository,
    ) {
    }

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
            $this->newsletterRepository->save($subscriber);
        }

        return new SubscribeResponse($subscriber);
    }
}
