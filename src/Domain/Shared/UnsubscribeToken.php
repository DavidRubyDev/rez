<?php

declare(strict_types=1);

namespace Rez\Domain\Shared;

final class UnsubscribeToken
{
    private function __construct(private readonly string $value)
    {
    }

    public static function generate(string $email, string $secret): self
    {
        return new self(hash_hmac('sha256', "unsubscribe:{$email}", $secret));
    }

    public static function fromString(string $token): self
    {
        return new self($token);
    }

    public function verify(string $email, string $secret): bool
    {
        $expected = hash_hmac('sha256', "unsubscribe:{$email}", $secret);

        return hash_equals($expected, $this->value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
