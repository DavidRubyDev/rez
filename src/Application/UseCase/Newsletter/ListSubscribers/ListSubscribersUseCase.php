<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\ListSubscribers;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\NewsletterRepositoryInterface;

final class ListSubscribersUseCase implements ListSubscribersUseCaseInterface
{
    public function __construct(
        private readonly NewsletterRepositoryInterface $newsletterRepository,
    ) {
    }

    /** @throws DatabaseException */
    public function execute(ListSubscribersRequest $request): ListSubscribersResponse
    {
        try {
            $subscribers = $this->newsletterRepository->findAll();
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load newsletter subscribers.', 0, $e);
        }

        return new ListSubscribersResponse($subscribers);
    }
}
