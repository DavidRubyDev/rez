<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

use Rez\Domain\Shared\Currency;

final class InsufficientFundsException extends DomainException
{
    public function __construct(int $required, int $available, Currency $currency)
    {
        $code = $currency->getCode();
        parent::__construct(
            "Insufficient funds: required {$required} {$code}, available {$available} {$code}."
        );
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::InsufficientFunds;
    }
}
