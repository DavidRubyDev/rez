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

    public function testCzkMapsToString(): void
    {
        $this->assertSame('czk', $this->mapper->toString(Currency::Czk));
    }

    public function testEurMapsToString(): void
    {
        $this->assertSame('eur', $this->mapper->toString(Currency::Eur));
    }

    public function testUsdMapsToString(): void
    {
        $this->assertSame('usd', $this->mapper->toString(Currency::Usd));
    }

    public function testFromStringReturnsCzk(): void
    {
        $this->assertSame(Currency::Czk, $this->mapper->fromString('czk'));
    }

    public function testFromStringReturnsEur(): void
    {
        $this->assertSame(Currency::Eur, $this->mapper->fromString('eur'));
    }

    public function testFromStringReturnsUsd(): void
    {
        $this->assertSame(Currency::Usd, $this->mapper->fromString('usd'));
    }

    public function testFromStringThrowsForUnknownValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mapper->fromString('gbp');
    }
}
