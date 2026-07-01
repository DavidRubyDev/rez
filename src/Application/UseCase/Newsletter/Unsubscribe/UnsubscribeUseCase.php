<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Unsubscribe;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Domain\Exception\NewsletterSubscriberNotFoundException;

final class UnsubscribeUseCase implements UnsubscribeUseCaseInterface
{
    public function __construct(
        private readonly NewsletterRepositoryInterface $newsletterRepository,
    ) {
    }

    /** @throws DatabaseException */
    public function execute(UnsubscribeRequest $request): UnsubscribeResponse
    {
        try {
            $subscriber = $this->newsletterRepository->findByEmail($request->email);
        } catch (NewsletterSubscriberNotFoundException) {
            return new UnsubscribeResponse(removed: false);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load subscriber.', 0, $e);
        }

        try {
            $this->newsletterRepository->delete($subscriber->id);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to delete subscriber.', 0, $e);
        }

        return new UnsubscribeResponse(removed: true);
    }
}
