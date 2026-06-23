<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Subscribe;

use Rez\Domain\Newsletter\NewsletterSubscriber;

final class SubscribeResponse
{
    public function __construct(
        public readonly NewsletterSubscriber $subscriber,
    ) {
    }
}
