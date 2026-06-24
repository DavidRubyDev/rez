<?php

declare(strict_types=1);

namespace Rez\Domain\Newsletter;

final class NewsletterSubscriberId
{
    private function __construct(
        private readonly string $id,
    ) {
    }

    public static function generate(): self
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return new self(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4)));
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromString(string $id): self
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid UUID v4.', $id));
        }

        return new self($id);
    }

    public function toString(): string
    {
        return $this->id;
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
