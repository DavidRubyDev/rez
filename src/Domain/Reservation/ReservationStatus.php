<?php

declare(strict_types=1);

namespace Rez\Domain\Reservation;

enum ReservationStatus
{
    case Pending;
    case Confirmed;
    case Cancelled;
    case NoShow;
}
