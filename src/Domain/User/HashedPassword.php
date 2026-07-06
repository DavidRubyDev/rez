<?php

declare(strict_types=1);

namespace Rez\Domain\User;

final class HashedPassword
{
    private function __construct(
        private readonly string $hash,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromPlainText(string $plainText): self
    {
        if ($plainText === '') {
            throw new \InvalidArgumentException('Password must not be empty.');
        }

        return new self(password_hash($plainText, PASSWORD_BCRYPT));
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromHash(string $hash): self
    {
        if ($hash === '') {
            throw new \InvalidArgumentException('Hash must not be empty.');
        }

        return new self($hash);
    }

    public function verify(string $plainText): bool
    {
        return password_verify($plainText, $this->hash);
    }

    public function toString(): string
    {
        return $this->hash;
    }

    public function __toString(): string
    {
        return $this->hash;
    }
}
