<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use DateTimeImmutable;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationCollection;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Session\SessionId;

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
    public function findBySessionId(SessionId $sessionId): ReservationCollection;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function findAll(?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): ReservationCollection;

    /**
     * @param ReservationStatus[]|null $excludeStatuses
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findPage(
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        ?DateTimeImmutable $startsBefore = null,
        ?DateTimeImmutable $createdFrom = null,
        ?DateTimeImmutable $createdTo = null,
        ?ResourceId $resourceId = null,
        ?ReservationStatus $status = null,
        ?array $excludeStatuses = null,
        ?string $search = null,
        ?int $offset = null,
        ?int $limit = null,
        ?string $sortBy = null,
        ?string $sortDir = null,
    ): ReservationCollection;

    /**
     * @param ReservationStatus[]|null $excludeStatuses
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function countPage(
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        ?DateTimeImmutable $startsBefore = null,
        ?DateTimeImmutable $createdFrom = null,
        ?DateTimeImmutable $createdTo = null,
        ?ResourceId $resourceId = null,
        ?ReservationStatus $status = null,
        ?array $excludeStatuses = null,
        ?string $search = null,
    ): int;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function save(Reservation $reservation): void;
}
