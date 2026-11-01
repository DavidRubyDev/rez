<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Mapper;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Payment\PaymentMethod;
use Rez\Infrastructure\Mapper\PaymentMethodMapper;

class PaymentMethodMapperTest extends TestCase
{
    private PaymentMethodMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new PaymentMethodMapper();
    }

    public function testCashAtCashierMapsToString(): void
    {
        $this->assertSame('cash_at_cashier', $this->mapper->toString(PaymentMethod::CashAtCashier));
    }

    public function testStringMapsToCashAtCashier(): void
    {
        $this->assertSame(PaymentMethod::CashAtCashier, $this->mapper->fromString('cash_at_cashier'));
    }

    public function testUnknownStringThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mapper->fromString('unknown');
    }
}
