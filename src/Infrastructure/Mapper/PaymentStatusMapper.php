<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Mapper;

use Rez\Domain\Payment\PaymentStatus;

final class PaymentStatusMapper
{
    public function toString(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::Unpaid => 'unpaid',
            PaymentStatus::Paid   => 'paid',
        };
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function fromString(string $value): PaymentStatus
    {
        return match ($value) {
            'unpaid' => PaymentStatus::Unpaid,
            'paid'   => PaymentStatus::Paid,
            default  => throw new \InvalidArgumentException(sprintf('Unknown PaymentStatus value: "%s".', $value)),
        };
    }
}
