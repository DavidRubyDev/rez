<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use DateTimeImmutable;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationCollection;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;

interface ReservationRepositoryInterface
{
    /**
     * @throws \Rez\Domain\Exception\ReservationNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findById(ReservationId $id): Reservation;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function findByTimeSlotAndResource(TimeSlot $slot, ResourceId $resourceId): ReservationCollection;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function findAll(?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): ReservationCollection;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function save(Reservation $reservation): void;
}
