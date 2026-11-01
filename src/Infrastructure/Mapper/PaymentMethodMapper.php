<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Mapper;

use Rez\Domain\Payment\PaymentMethod;

final class PaymentMethodMapper
{
    public function toString(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::CashAtCashier => 'cash_at_cashier',
        };
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function fromString(string $value): PaymentMethod
    {
        return match ($value) {
            'cash_at_cashier' => PaymentMethod::CashAtCashier,
            default           => throw new \InvalidArgumentException(sprintf('Unknown PaymentMethod value: "%s".', $value)),
        };
    }
}
