<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Mapper;

use Rez\Domain\Reservation\ReservationStatus;

final class ReservationStatusMapper
{
    public function toString(ReservationStatus $status): string
    {
        return match ($status) {
            ReservationStatus::Pending   => 'pending',
            ReservationStatus::Confirmed => 'confirmed',
            ReservationStatus::Cancelled => 'cancelled',
            ReservationStatus::NoShow    => 'no_show',
            ReservationStatus::CheckedIn => 'checked_in',
        };
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function fromString(string $value): ReservationStatus
    {
        return match ($value) {
            'pending'    => ReservationStatus::Pending,
            'confirmed'  => ReservationStatus::Confirmed,
            'cancelled'  => ReservationStatus::Cancelled,
            'no_show'    => ReservationStatus::NoShow,
            'checked_in' => ReservationStatus::CheckedIn,
            default   => throw new \InvalidArgumentException(sprintf('Unknown ReservationStatus value: "%s".', $value)),
        };
    }
}
