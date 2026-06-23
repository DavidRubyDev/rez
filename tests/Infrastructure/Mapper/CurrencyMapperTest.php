<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Mapper;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Shared\Currency;
use Rez\Infrastructure\Mapper\CurrencyMapper;

class CurrencyMapperTest extends TestCase
{
    private CurrencyMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new CurrencyMapper();
    }

    public function testFromStringReturnsCzk(): void
    {
        $this->assertSame(Currency::Czk, $this->mapper->fromString('CZK'));
    }

    public function testFromStringReturnsEur(): void
    {
        $this->assertSame(Currency::Eur, $this->mapper->fromString('EUR'));
    }

    public function testFromStringReturnsUsd(): void
    {
        $this->assertSame(Currency::Usd, $this->mapper->fromString('USD'));
    }

    public function testFromStringIsCaseInsensitive(): void
    {
        $this->assertSame(Currency::Czk, $this->mapper->fromString('czk'));
        $this->assertSame(Currency::Eur, $this->mapper->fromString('eur'));
    }

    public function testFromStringThrowsForUnknownValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mapper->fromString('GBP');
    }
}
