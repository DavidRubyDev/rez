<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Mapper;

use Rez\Domain\Newsletter\SubscriberSource;

final class SubscriberSourceMapper
{
    public function toString(SubscriberSource $source): string
    {
        return match ($source) {
            SubscriberSource::Guest       => 'guest',
            SubscriberSource::Registered  => 'registered',
            SubscriberSource::Admin       => 'admin',
        };
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function fromString(string $value): SubscriberSource
    {
        return match ($value) {
            'guest'      => SubscriberSource::Guest,
            'registered' => SubscriberSource::Registered,
            'admin'      => SubscriberSource::Admin,
            default      => throw new \InvalidArgumentException(sprintf('Unknown SubscriberSource value: "%s".', $value)),
        };
    }
}
