<?php

declare(strict_types=1);

namespace Rez\Domain\Reservation;

use Rez\Domain\Exception\InvalidPartyException;

final class Party
{
    public function __construct(
        private readonly string $name,
        private readonly string $email,
        private readonly int $size,
        private readonly ?string $phone,
    ) {
        if ($name === '') {
            throw new InvalidPartyException('Party name must not be empty.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidPartyException(sprintf('"%s" is not a valid email address.', $email));
        }

        if ($size < 1) {
            throw new InvalidPartyException(sprintf('Party size must be at least 1, got %d.', $size));
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }
}
