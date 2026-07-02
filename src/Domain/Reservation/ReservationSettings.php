<?php

declare(strict_types=1);

namespace Rez\Domain\Reservation;

final class ReservationSettings
{
    public function __construct(
        public readonly bool $autoConfirm,
        public readonly bool $autoSendReservationCreated,
        public readonly bool $autoSendReservationConfirmed,
        public readonly bool $autoSendReservationCancelled,
    ) {
    }
}
