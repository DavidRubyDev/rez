<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Token;

use Rez\Application\Port\TokenGeneratorInterface;

final class RandomTokenGenerator implements TokenGeneratorInterface
{
    /**
     * @throws \InvalidArgumentException
     */
    public function generate(int $bytes = 32): string
    {
        if ($bytes < 1) {
            throw new \InvalidArgumentException("bytes must be at least 1, got {$bytes}.");
        }

        return bin2hex(random_bytes($bytes));
    }
}
