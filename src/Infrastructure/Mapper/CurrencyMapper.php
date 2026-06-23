<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Mapper;

use Rez\Domain\Shared\Currency;

final class CurrencyMapper
{
    public function fromString(string $currency): Currency
    {
        return match (strtoupper($currency)) {
            'CZK' => Currency::Czk,
            'EUR' => Currency::Eur,
            'USD' => Currency::Usd,
            default => throw new \InvalidArgumentException("Unknown currency: '{$currency}'."),
        };
    }
}
