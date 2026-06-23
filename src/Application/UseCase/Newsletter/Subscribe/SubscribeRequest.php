<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Subscribe;

use Rez\Domain\Newsletter\SubscriberSource;

final class SubscribeRequest
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $name,
        public readonly SubscriberSource $source,
    ) {
    }
}
