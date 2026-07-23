<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\CancelSession;

use Rez\Application\UseCase\Reservation\BulkCancelReservations\BulkCancelReservationsResponse;
use Rez\Domain\Session\Session;

final class CancelSessionResponse
{
    public function __construct(
        public readonly Session $session,
        public readonly BulkCancelReservationsResponse $cancelledReservations,
    ) {
    }
}
