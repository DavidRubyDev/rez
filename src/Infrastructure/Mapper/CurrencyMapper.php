<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Mapper;

use Rez\Domain\Shared\Currency;

final class CurrencyMapper
{
    public function toString(Currency $currency): string
    {
        return match ($currency) {
            Currency::Czk => 'czk',
            Currency::Eur => 'eur',
            Currency::Usd => 'usd',
        };
    }

    public function fromString(string $currency): Currency
    {
        return match ($currency) {
            'czk' => Currency::Czk,
            'eur' => Currency::Eur,
            'usd' => Currency::Usd,
            default => throw new \InvalidArgumentException("Unknown currency: '{$currency}'."),
        };
    }
}
