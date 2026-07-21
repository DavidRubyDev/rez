<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\ListSubscribers;

use Rez\Domain\Newsletter\NewsletterSubscriber;

final class ListSubscribersResponse
{
    /** @param NewsletterSubscriber[] $subscribers */
    public function __construct(
        public readonly array $subscribers,
        public readonly int $total,
    ) {
    }
}
