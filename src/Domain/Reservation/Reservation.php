<?php

declare(strict_types=1);

namespace Rez\Domain\Reservation;

use DateTimeImmutable;
use Rez\Domain\Exception\InvalidReservationStateException;
use Rez\Domain\Resource\ResourceIdCollection;

final class Reservation
{
    private function __construct(
        public readonly ReservationId $id,
        public readonly ResourceIdCollection $resourceIds,
        public readonly TimeSlot $slot,
        public readonly Party $party,
        public readonly ReservationStatus $status,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
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

    /**
     * @throws InvalidReservationStateException
     */
    public function cancel(): self
    {
        if ($this->status === ReservationStatus::Cancelled) {
            throw new InvalidReservationStateException('Reservation is already cancelled.');
        }

        return new self($this->id, $this->resourceIds, $this->slot, $this->party, ReservationStatus::Cancelled, $this->createdAt);
    }

    /**
     * @throws InvalidReservationStateException
     */
    public function confirm(): self
    {
        if ($this->status !== ReservationStatus::Pending) {
            throw new InvalidReservationStateException('Only a pending reservation can be confirmed.');
        }

        return new self($this->id, $this->resourceIds, $this->slot, $this->party, ReservationStatus::Confirmed, $this->createdAt);
    }

    /**
     * @throws InvalidReservationStateException
     */
    public function markNoShow(): self
    {
        if ($this->status !== ReservationStatus::Confirmed) {
            throw new InvalidReservationStateException('Only a confirmed reservation can be marked as no-show.');
        }

        return new self($this->id, $this->resourceIds, $this->slot, $this->party, ReservationStatus::NoShow, $this->createdAt);
    }
}
