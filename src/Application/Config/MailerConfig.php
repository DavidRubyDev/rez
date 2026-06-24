<?php

declare(strict_types=1);

namespace Rez\Application\Config;

final class MailerConfig
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public readonly string $fromAddress,
        public readonly string $fromName,
    ) {
        if (filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException("Invalid email address: '{$fromAddress}'.");
        }

        if ($fromName === '') {
            throw new \InvalidArgumentException('fromName must not be empty.');
        }
    }
}
