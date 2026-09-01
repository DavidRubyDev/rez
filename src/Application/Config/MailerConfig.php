<?php

declare(strict_types=1);

namespace Rez\Application\Config;

final class MailerConfig
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public readonly string $cancellationBaseUrl,
        public readonly string $unsubscribeBaseUrl,
    ) {
        if ($cancellationBaseUrl === '') {
            throw new \InvalidArgumentException('cancellationBaseUrl must not be empty.');
        }

        if ($unsubscribeBaseUrl === '') {
            throw new \InvalidArgumentException('unsubscribeBaseUrl must not be empty.');
        }
    }
}
