<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\MarkReservationPaid;

use Rez\Domain\Payment\PaymentMethod;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Shared\Money;

final class MarkReservationPaidRequest
{
    public function __construct(
        public readonly ReservationId $reservationId,
        public readonly Money $amount,
        public readonly PaymentMethod $method,
    ) {
    }
}
