<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Shared;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Exception\InsufficientFundsException;
use Rez\Domain\Shared\Currency;
use Rez\Domain\Shared\Money;

class MoneyTest extends TestCase
{
    public function testValidConstructionStoresValues(): void
    {
        $money = new Money(15000, Currency::Czk);

        $this->assertSame(15000, $money->getAmount());
        $this->assertSame(Currency::Czk, $money->getCurrency());
    }

    public function testNegativeAmountThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Money(-1, Currency::Czk);
    }

    public function testAddWithSameCurrencyReturnsSum(): void
    {
        $a = new Money(10000, Currency::Czk);
        $b = new Money(5000, Currency::Czk);

        $result = $a->add($b);

        $this->assertSame(15000, $result->getAmount());
        $this->assertSame(Currency::Czk, $result->getCurrency());
    }

    public function testAddWithDifferentCurrenciesThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Money(10000, Currency::Czk))->add(new Money(5000, Currency::Eur));
    }

    public function testSubtractWithSufficientAmountReturnsResult(): void
    {
        $a = new Money(10000, Currency::Czk);
        $b = new Money(3000, Currency::Czk);

        $result = $a->subtract($b);

        $this->assertSame(7000, $result->getAmount());
    }

    public function testSubtractResultingInZeroIsValid(): void
    {
        $a = new Money(5000, Currency::Czk);
        $b = new Money(5000, Currency::Czk);

        $result = $a->subtract($b);

        $this->assertSame(0, $result->getAmount());
        $this->assertTrue($result->isZero());
    }

    public function testSubtractBelowZeroThrowsInsufficientFundsException(): void
    {
        $this->expectException(InsufficientFundsException::class);
        (new Money(3000, Currency::Czk))->subtract(new Money(5000, Currency::Czk));
    }

    public function testIsZeroTrueWhenAmountIsZero(): void
    {
        $this->assertTrue((new Money(0, Currency::Eur))->isZero());
    }

    public function testIsZeroFalseWhenAmountIsPositive(): void
    {
        $this->assertFalse((new Money(1, Currency::Eur))->isZero());
    }

    public function testEqualsReturnsTrueForIdenticalAmountAndCurrency(): void
    {
        $a = new Money(5000, Currency::Usd);
        $b = new Money(5000, Currency::Usd);

        $this->assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentAmount(): void
    {
        $this->assertFalse((new Money(5000, Currency::Czk))->equals(new Money(6000, Currency::Czk)));
    }

    public function testEqualsReturnsFalseForDifferentCurrency(): void
    {
        $this->assertFalse((new Money(5000, Currency::Czk))->equals(new Money(5000, Currency::Eur)));
    }

    public function testIsGreaterThanReturnsTrueWhenHigher(): void
    {
        $this->assertTrue((new Money(6000, Currency::Czk))->isGreaterThan(new Money(5000, Currency::Czk)));
    }

    public function testIsGreaterThanReturnsFalseWhenEqualOrLess(): void
    {
        $this->assertFalse((new Money(5000, Currency::Czk))->isGreaterThan(new Money(5000, Currency::Czk)));
        $this->assertFalse((new Money(4000, Currency::Czk))->isGreaterThan(new Money(5000, Currency::Czk)));
    }

    public function testIsGreaterThanWithDifferentCurrenciesThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Money(6000, Currency::Czk))->isGreaterThan(new Money(5000, Currency::Eur));
    }

    public function testToStringReturnsCorrectFormat(): void
    {
        $this->assertSame('150000 CZK', (string) new Money(150000, Currency::Czk));
        $this->assertSame('500 EUR', (string) new Money(500, Currency::Eur));
    }
}
