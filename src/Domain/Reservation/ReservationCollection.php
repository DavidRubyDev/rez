<?php

declare(strict_types=1);

namespace Rez\Domain\Reservation;

final class ReservationCollection
{
    /** @param list<Reservation> $reservations */
    private function __construct(
        private readonly array $reservations = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /** @param list<Reservation> $reservations */
    public static function fromArray(array $reservations): self
    {
        return new self($reservations);
    }

    public function add(Reservation $reservation): self
    {
        return new self([...$this->reservations, $reservation]);
    }

    public function isEmpty(): bool
    {
        return $this->reservations === [];
    }

    public function count(): int
    {
        return count($this->reservations);
    }

    /** @return list<Reservation> */
    public function toArray(): array
    {
        return $this->reservations;
    }

    public function filter(callable $fn): self
    {
        return new self(array_values(array_filter($this->reservations, $fn)));
    }

    public function filterByStatus(ReservationStatus $status): self
    {
        return $this->filter(fn (Reservation $r) => $r->status === $status);
    }

    public function findById(ReservationId $id): ?Reservation
    {
        foreach ($this->reservations as $reservation) {
            if ($reservation->id->equals($id)) {
                return $reservation;
            }
        }

        return null;
    }
}
