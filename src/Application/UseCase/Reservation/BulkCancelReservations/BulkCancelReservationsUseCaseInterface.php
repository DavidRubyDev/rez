<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\BulkCancelReservations;

interface BulkCancelReservationsUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(BulkCancelReservationsRequest $request): BulkCancelReservationsResponse;
}
