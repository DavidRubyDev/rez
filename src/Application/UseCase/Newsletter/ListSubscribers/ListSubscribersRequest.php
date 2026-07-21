<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\ListSubscribers;

use Rez\Domain\Newsletter\SubscriberSource;

final class ListSubscribersRequest
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?SubscriberSource $source = null,
        public readonly ?int $offset = null,
        public readonly ?int $limit = null,
        public readonly ?string $sortBy = null,
        public readonly ?string $sortDir = null,
    ) {
    }
}
