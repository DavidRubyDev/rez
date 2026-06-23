<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Unsubscribe;

use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Domain\Exception\NewsletterSubscriberNotFoundException;

final class UnsubscribeUseCase implements UnsubscribeUseCaseInterface
{
    public function __construct(
        private readonly NewsletterRepositoryInterface $newsletterRepository,
    ) {
    }

    public function execute(UnsubscribeRequest $request): UnsubscribeResponse
    {
        try {
            $subscriber = $this->newsletterRepository->findByEmail($request->email);
        } catch (NewsletterSubscriberNotFoundException) {
            return new UnsubscribeResponse(removed: false);
        }

        $this->newsletterRepository->delete($subscriber->id);

        return new UnsubscribeResponse(removed: true);
    }
}
