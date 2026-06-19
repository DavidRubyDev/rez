<?php

declare(strict_types=1);

namespace Rez\Domain\Reservation;

use DateTimeImmutable;
use Rez\Domain\Exception\InvalidReservationStateException;
use Rez\Domain\Resource\ResourceId;

final class Reservation
{
    /** @param ResourceId[] $resourceIds */
    private function __construct(
        private readonly ReservationId $id,
        private readonly array $resourceIds,
        private readonly TimeSlot $slot,
        private readonly Party $party,
        private readonly ReservationStatus $status,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /** @param ResourceId[] $resourceIds */
    public static function create(
        ReservationId $id,
        array $resourceIds,
        TimeSlot $slot,
        Party $party,
    ): self {
        if ($resourceIds === []) {
            throw new \InvalidArgumentException('A reservation must have at least one resource.');
        }

        return new self($id, $resourceIds, $slot, $party, ReservationStatus::Pending, new DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    public function id(): ReservationId
    {
        return $this->id;
    }

    /** @return ResourceId[] */
    public function resourceIds(): array
    {
        return $this->resourceIds;
    }

    public function slot(): TimeSlot
    {
        return $this->slot;
    }

    public function party(): Party
    {
        return $this->party;
    }

    public function status(): ReservationStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function cancel(): self
    {
        if ($this->status === ReservationStatus::Cancelled) {
            throw new InvalidReservationStateException('Reservation is already cancelled.');
        }

        return new self($this->id, $this->resourceIds, $this->slot, $this->party, ReservationStatus::Cancelled, $this->createdAt);
    }

    public function confirm(): self
    {
        if ($this->status !== ReservationStatus::Pending) {
            throw new InvalidReservationStateException('Only a pending reservation can be confirmed.');
        }

        return new self($this->id, $this->resourceIds, $this->slot, $this->party, ReservationStatus::Confirmed, $this->createdAt);
    }

    public function markNoShow(): self
    {
        if ($this->status !== ReservationStatus::Confirmed) {
            throw new InvalidReservationStateException('Only a confirmed reservation can be marked as no-show.');
        }

        return new self($this->id, $this->resourceIds, $this->slot, $this->party, ReservationStatus::NoShow, $this->createdAt);
    }
}
