<?php

declare(strict_types=1);

namespace Rez\Domain\Shared;

use Rez\Domain\Reservation\ReservationId;

final class CancellationToken
{
    private function __construct(private readonly string $value)
    {
    }

    public static function generate(ReservationId $id, string $secret): self
    {
        return new self(hash_hmac('sha256', $id->toString(), $secret));
    }

    public static function fromString(string $token): self
    {
        return new self($token);
    }

    public function verify(ReservationId $id, string $secret): bool
    {
        $expected = hash_hmac('sha256', $id->toString(), $secret);

        return hash_equals($expected, $this->value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
