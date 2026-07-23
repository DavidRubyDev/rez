<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\ListReservations;

use DateTimeImmutable;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Resource\ResourceId;

final class ListReservationsRequest
{
    public function __construct(
        public readonly ?DateTimeImmutable $from = null,
        public readonly ?DateTimeImmutable $to = null,
        public readonly ?DateTimeImmutable $createdFrom = null,
        public readonly ?DateTimeImmutable $createdTo = null,
        public readonly ?ResourceId $resourceId = null,
        public readonly ?ReservationStatus $status = null,
        public readonly ?string $search = null,
        public readonly ?int $offset = null,
        public readonly ?int $limit = null,
        public readonly ?string $sortBy = null,
        public readonly ?string $sortDir = null,
    ) {
    }
}
