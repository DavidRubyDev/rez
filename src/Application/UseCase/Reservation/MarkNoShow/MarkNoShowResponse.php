<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\MarkNoShow;

use Rez\Domain\Reservation\Reservation;

final class MarkNoShowResponse
{
    public function __construct(
        public readonly Reservation $reservation,
    ) {
    }
}
