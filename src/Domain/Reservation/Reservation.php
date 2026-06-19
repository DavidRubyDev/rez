<?php

declare(strict_types=1);

namespace Rez\Domain\Reservation;

use DateTimeImmutable;
use Rez\Domain\Exception\InvalidReservationStateException;
use Rez\Domain\Resource\ResourceIdCollection;

final class Reservation
{
    private function __construct(
        private readonly ReservationId $id,
        private readonly ResourceIdCollection $resourceIds,
        private readonly TimeSlot $slot,
        private readonly Party $party,
        private readonly ReservationStatus $status,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        ReservationId $id,
        ResourceIdCollection $resourceIds,
        TimeSlot $slot,
        Party $party,
    ): self {
        if ($resourceIds->isEmpty()) {
            throw new \InvalidArgumentException('A reservation must have at least one resource.');
        }

        return new self($id, $resourceIds, $slot, $party, ReservationStatus::Pending, new DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    public static function reconstruct(
        ReservationId $id,
        ResourceIdCollection $resourceIds,
        TimeSlot $slot,
        Party $party,
        ReservationStatus $status,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $resourceIds, $slot, $party, $status, $createdAt);
    }

    public function id(): ReservationId
    {
        return $this->id;
    }

    public function resourceIds(): ResourceIdCollection
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
