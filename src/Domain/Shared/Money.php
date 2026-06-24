<?php

declare(strict_types=1);

namespace Rez\Domain\Shared;

use Rez\Domain\Exception\InsufficientFundsException;

final class Money
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public readonly int $amount,
        public readonly Currency $currency,
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException("Money amount must not be negative, got {$amount}.");
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * @throws InsufficientFundsException
     * @throws \InvalidArgumentException
     */
    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        if ($other->amount > $this->amount) {
            throw new InsufficientFundsException($other->amount, $this->amount, $this->currency);
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function isGreaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function __toString(): string
    {
        return "{$this->amount} {$this->currency->getCode()}";
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Currency mismatch: {$this->currency->getCode()} vs {$other->currency->getCode()}."
            );
        }
    }
}
