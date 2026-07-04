<?php

declare(strict_types=1);

namespace Rez\Domain\User;

final class UserCollection
{
    /** @param User[] $users */
    private function __construct(
        private readonly array $users = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /** @param User[] $users */
    public static function fromArray(array $users): self
    {
        return new self($users);
    }

    public function add(User $user): self
    {
        return new self([...$this->users, $user]);
    }

    public function isEmpty(): bool
    {
        return $this->users === [];
    }

    public function count(): int
    {
        return count($this->users);
    }

    /** @return User[] */
    public function toArray(): array
    {
        return $this->users;
    }

    public function filter(callable $fn): self
    {
        return new self(array_values(array_filter($this->users, $fn)));
    }

    public function findById(UserId $id): ?User
    {
        foreach ($this->users as $user) {
            if ($user->id->equals($id)) {
                return $user;
            }
        }

        return null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email === $email) {
                return $user;
            }
        }

        return null;
    }
}
