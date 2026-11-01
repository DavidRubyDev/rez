<?php

declare(strict_types=1);

namespace Rez\Domain\Session;

use DateTimeImmutable;
use Rez\Domain\Exception\InvalidSessionStateException;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Shared\Money;

final class Session
{
    private function __construct(
        public readonly SessionId $id,
        public readonly ResourceId $resourceId,
        public readonly DateTimeImmutable $startTime,
        public readonly int $durationMinutes,
        public readonly int $capacity,
        public readonly SessionStatus $status,
        public readonly ?Money $price = null,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function create(
        SessionId $id,
        ResourceId $resourceId,
        DateTimeImmutable $startTime,
        int $durationMinutes,
        int $capacity,
        ?Money $price = null,
    ): self {
        if ($durationMinutes <= 0) {
            throw new \InvalidArgumentException(sprintf('Session duration must be greater than zero, got %d.', $durationMinutes));
        }

        if ($capacity <= 0) {
            throw new \InvalidArgumentException(sprintf('Session capacity must be greater than zero, got %d.', $capacity));
        }

        return new self($id, $resourceId, $startTime, $durationMinutes, $capacity, SessionStatus::Scheduled, $price);
    }

    public static function reconstruct(
        SessionId $id,
        ResourceId $resourceId,
        DateTimeImmutable $startTime,
        int $durationMinutes,
        int $capacity,
        SessionStatus $status,
        ?Money $price = null,
    ): self {
        return new self($id, $resourceId, $startTime, $durationMinutes, $capacity, $status, $price);
    }

    /**
     * @throws InvalidSessionStateException
     */
    public function cancel(): self
    {
        if ($this->status === SessionStatus::Cancelled) {
            throw new InvalidSessionStateException('Session is already cancelled.');
        }

        return new self($this->id, $this->resourceId, $this->startTime, $this->durationMinutes, $this->capacity, SessionStatus::Cancelled, $this->price);
    }

    public function toTimeSlot(): TimeSlot
    {
        return new TimeSlot($this->startTime, $this->startTime->modify("+{$this->durationMinutes} minutes"));
    }
}
