<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Mapper;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Payment\PaymentStatus;
use Rez\Infrastructure\Mapper\PaymentStatusMapper;

class PaymentStatusMapperTest extends TestCase
{
    private PaymentStatusMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new PaymentStatusMapper();
    }

    public function testUnpaidMapsToString(): void
    {
        $this->assertSame('unpaid', $this->mapper->toString(PaymentStatus::Unpaid));
    }

    public function testPaidMapsToString(): void
    {
        $this->assertSame('paid', $this->mapper->toString(PaymentStatus::Paid));
    }

    public function testStringMapsToUnpaid(): void
    {
        $this->assertSame(PaymentStatus::Unpaid, $this->mapper->fromString('unpaid'));
    }

    public function testStringMapsToPaid(): void
    {
        $this->assertSame(PaymentStatus::Paid, $this->mapper->fromString('paid'));
    }

    public function testUnknownStringThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mapper->fromString('unknown');
    }
}
