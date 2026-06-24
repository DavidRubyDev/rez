<?php

declare(strict_types=1);

namespace Rez\Application\Config;

final class CreditsConfig
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public readonly int $minimumTopUpAmount,
        public readonly string $currency,
    ) {
        if ($minimumTopUpAmount < 1) {
            throw new \InvalidArgumentException("minimumTopUpAmount must be at least 1, got {$minimumTopUpAmount}.");
        }

        if ($currency === '') {
            throw new \InvalidArgumentException('currency must not be empty.');
        }
    }
}
