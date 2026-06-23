<?php

declare(strict_types=1);

namespace Rez\Application\Config;

final class PaymentsConfig
{
    public function __construct(
        public readonly string $currency,
        public readonly string $webhookSecret,
    ) {
        if ($currency === '') {
            throw new \InvalidArgumentException('currency must not be empty.');
        }

        if ($webhookSecret === '') {
            throw new \InvalidArgumentException('webhookSecret must not be empty.');
        }
    }
}
