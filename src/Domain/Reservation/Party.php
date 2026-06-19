<?php

declare(strict_types=1);

namespace Rez\Domain\Reservation;

use Rez\Domain\Exception\InvalidPartyException;

final class Party
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly int $size,
        public readonly ?string $phone,
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

}
