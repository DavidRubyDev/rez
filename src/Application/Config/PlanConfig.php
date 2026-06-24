<?php

declare(strict_types=1);

namespace Rez\Application\Config;

final class PlanConfig
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $priceAmount,
        public readonly string $currency,
        public readonly int $intervalDays,
        public readonly string $stripePriceId,
    ) {
        if ($id === '') {
            throw new \InvalidArgumentException('id must not be empty.');
        }

        if ($name === '') {
            throw new \InvalidArgumentException('name must not be empty.');
        }

        if ($priceAmount < 0) {
            throw new \InvalidArgumentException("priceAmount must not be negative, got {$priceAmount}.");
        }

        if ($currency === '') {
            throw new \InvalidArgumentException('currency must not be empty.');
        }

        if ($intervalDays < 1) {
            throw new \InvalidArgumentException("intervalDays must be at least 1, got {$intervalDays}.");
        }

        if ($stripePriceId === '') {
            throw new \InvalidArgumentException('stripePriceId must not be empty.');
        }
    }
}
