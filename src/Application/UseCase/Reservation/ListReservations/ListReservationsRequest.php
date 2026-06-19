<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\ListReservations;

use DateTimeImmutable;
use Rez\Domain\Resource\ResourceId;

final class ListReservationsRequest
{
    public function __construct(
        public readonly ?DateTimeImmutable $from = null,
        public readonly ?DateTimeImmutable $to = null,
        public readonly ?ResourceId $resourceId = null,
    ) {
    }
}
