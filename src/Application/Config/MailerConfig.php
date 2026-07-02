<?php

declare(strict_types=1);

namespace Rez\Application\Config;

final class MailerConfig
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public readonly string $cancellationSecret,
    ) {
        if ($cancellationSecret === '') {
            throw new \InvalidArgumentException('cancellationSecret must not be empty.');
        }
    }
}
