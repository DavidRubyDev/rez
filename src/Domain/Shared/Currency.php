<?php

declare(strict_types=1);

namespace Rez\Domain\Shared;

enum Currency
{
    case Czk;
    case Eur;
    case Usd;

    public function getCode(): string
    {
        return strtoupper($this->name);
    }
}
