<?php

declare(strict_types=1);

namespace Rez\Domain\Reservation;

use DateTimeImmutable;
use Rez\Domain\Exception\InvalidReservationStateException;
use Rez\Domain\Payment\PaymentMethod;
use Rez\Domain\Payment\PaymentStatus;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Domain\Session\SessionId;
use Rez\Domain\Shared\Money;
use Rez\Domain\Shared\Utc;

final class Reservation
{
    private function __construct(
        public readonly ReservationId $id,
        public readonly ResourceIdCollection $resourceIds,
        public readonly TimeSlot $slot,
        public readonly Party $party,
        public readonly ReservationStatus $status,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?SessionId $sessionId = null,
        public readonly ?DateTimeImmutable $checkedIn = null,
        public readonly PaymentStatus $paymentStatus = PaymentStatus::Unpaid,
        public readonly ?PaymentMethod $paymentMethod = null,
        public readonly ?Money $amountDue = null,
        public readonly ?DateTimeImmutable $paidAt = null,
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
        ?SessionId $sessionId = null,
        ?PaymentMethod $paymentMethod = null,
        ?Money $amountDue = null,
    ): self {
        if ($resourceIds->isEmpty()) {
            throw new \InvalidArgumentException('A reservation must have at least one resource.');
        }

        return new self($id, $resourceIds, $slot, $party, ReservationStatus::Pending, Utc::now(), $sessionId, paymentMethod: $paymentMethod, amountDue: $amountDue);
    }

    public static function reconstruct(
        ReservationId $id,
        ResourceIdCollection $resourceIds,
        TimeSlot $slot,
        Party $party,
        ReservationStatus $status,
        DateTimeImmutable $createdAt,
        ?SessionId $sessionId = null,
        ?DateTimeImmutable $checkedIn = null,
        PaymentStatus $paymentStatus = PaymentStatus::Unpaid,
        ?PaymentMethod $paymentMethod = null,
        ?Money $amountDue = null,
        ?DateTimeImmutable $paidAt = null,
    ): self {
        return new self($id, $resourceIds, $slot, $party, $status, $createdAt, $sessionId, $checkedIn, $paymentStatus, $paymentMethod, $amountDue, $paidAt);
    }

    /**
     * @throws InvalidReservationStateException
     */
    public function cancel(): self
    {
        if ($this->status === ReservationStatus::Cancelled) {
            throw new InvalidReservationStateException('Reservation is already cancelled.');
        }

        return new self($this->id, $this->resourceIds, $this->slot, $this->party, ReservationStatus::Cancelled, $this->createdAt, $this->sessionId, $this->checkedIn, $this->paymentStatus, $this->paymentMethod, $this->amountDue, $this->paidAt);
    }

    /**
     * @throws InvalidReservationStateException
     */
    public function confirm(): self
    {
        if ($this->status !== ReservationStatus::Pending) {
            throw new InvalidReservationStateException('Only a pending reservation can be confirmed.');
        }

        return new self($this->id, $this->resourceIds, $this->slot, $this->party, ReservationStatus::Confirmed, $this->createdAt, $this->sessionId, $this->checkedIn, $this->paymentStatus, $this->paymentMethod, $this->amountDue, $this->paidAt);
    }

    /**
     * @throws InvalidReservationStateException
     */
    public function markNoShow(): self
    {
        if ($this->status !== ReservationStatus::Confirmed && $this->status !== ReservationStatus::CheckedIn) {
            throw new InvalidReservationStateException('Only a confirmed or checked-in reservation can be marked as no-show.');
        }

        return new self($this->id, $this->resourceIds, $this->slot, $this->party, ReservationStatus::NoShow, $this->createdAt, $this->sessionId, checkedIn: null, paymentStatus: $this->paymentStatus, paymentMethod: $this->paymentMethod, amountDue: $this->amountDue, paidAt: $this->paidAt);
    }

    /**
     * @throws InvalidReservationStateException
     */
    public function checkIn(): self
    {
        if ($this->status !== ReservationStatus::Confirmed && $this->status !== ReservationStatus::NoShow) {
            throw new InvalidReservationStateException('Only a confirmed or no-show reservation can be checked in.');
        }

        return new self($this->id, $this->resourceIds, $this->slot, $this->party, ReservationStatus::CheckedIn, $this->createdAt, $this->sessionId, checkedIn: Utc::now(), paymentStatus: $this->paymentStatus, paymentMethod: $this->paymentMethod, amountDue: $this->amountDue, paidAt: $this->paidAt);
    }

    /**
     * @throws InvalidReservationStateException
     */
    public function markPaid(Money $amount, PaymentMethod $method): self
    {
        if ($this->paymentStatus === PaymentStatus::Paid) {
            throw new InvalidReservationStateException('Reservation is already paid.');
        }

        return new self($this->id, $this->resourceIds, $this->slot, $this->party, $this->status, $this->createdAt, $this->sessionId, $this->checkedIn, PaymentStatus::Paid, $method, $amount, Utc::now());
    }
}
