<?php

declare(strict_types=1);

namespace Rez\Application\Config;

use Rez\Domain\Shared\Email;

final class MailerConfig
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public readonly string $fromAddress,
        public readonly string $fromName,
    ) {
        if (!Email::isValid($fromAddress)) {
            throw new \InvalidArgumentException("Invalid email address: '{$fromAddress}'.");
        }

        if ($fromName === '') {
            throw new \InvalidArgumentException('fromName must not be empty.');
        }
    }
}
