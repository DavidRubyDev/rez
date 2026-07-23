<?php

declare(strict_types=1);

namespace Rez\Domain\Session;

final class SessionCollection
{
    /** @param Session[] $sessions */
    private function __construct(
        private readonly array $sessions = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /** @param Session[] $sessions */
    public static function fromArray(array $sessions): self
    {
        return new self($sessions);
    }

    public function add(Session $session): self
    {
        return new self([...$this->sessions, $session]);
    }

    public function isEmpty(): bool
    {
        return $this->sessions === [];
    }

    public function count(): int
    {
        return count($this->sessions);
    }

    /** @return Session[] */
    public function toArray(): array
    {
        return $this->sessions;
    }

    public function filter(callable $fn): self
    {
        return new self(array_values(array_filter($this->sessions, $fn)));
    }

    public function findById(SessionId $id): ?Session
    {
        foreach ($this->sessions as $session) {
            if ($session->id->equals($id)) {
                return $session;
            }
        }

        return null;
    }
}
