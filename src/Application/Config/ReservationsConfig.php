<?php

declare(strict_types=1);

namespace Rez\Application\Config;

final class ReservationsConfig
{
    public function __construct(
        public readonly bool $autoConfirm = false,
    ) {
    }
}
