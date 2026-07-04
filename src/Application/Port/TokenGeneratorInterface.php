<?php

declare(strict_types=1);

namespace Rez\Application\Port;

interface TokenGeneratorInterface
{
    /** @throws \InvalidArgumentException */
    public function generate(int $bytes = 32): string;
}
