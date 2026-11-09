<?php

declare(strict_types=1);

namespace Rez\Domain\Auth;

/**
 * Who failed logins are counted against: one email from one client, so an attacker can only
 * ever lock out their own attempts, never the real owner logging in from elsewhere. Kept as a
 * hash so the throttle store holds neither emails nor client addresses.
 */
final class LoginThrottleKey
{
    private function __construct(
        public readonly string $hash,
    ) {
    }

    public static function for(string $email, string $client): self
    {
        $normalizedEmail = mb_strtolower(trim($email));

        // Length-prefixed so no email/client split can collide with another.
        return new self(hash('sha256', strlen($normalizedEmail) . ':' . $normalizedEmail . $client));
    }

    public function equals(self $other): bool
    {
        return $this->hash === $other->hash;
    }
}
