<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Subscribe;

use Rez\Application\Exception\DatabaseException;
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
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load subscriber.', 0, $e);
        }

        return new SubscribeResponse($subscriber);
    }
}
