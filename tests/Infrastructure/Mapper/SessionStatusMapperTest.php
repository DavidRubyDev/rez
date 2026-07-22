<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Mapper;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Session\SessionStatus;
use Rez\Infrastructure\Mapper\SessionStatusMapper;

class SessionStatusMapperTest extends TestCase
{
    private SessionStatusMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new SessionStatusMapper();
    }

    public function testScheduledMapsToString(): void
    {
        $this->assertSame('scheduled', $this->mapper->toString(SessionStatus::Scheduled));
    }

    public function testCancelledMapsToString(): void
    {
        $this->assertSame('cancelled', $this->mapper->toString(SessionStatus::Cancelled));
    }

    public function testStringMapsToScheduled(): void
    {
        $this->assertSame(SessionStatus::Scheduled, $this->mapper->fromString('scheduled'));
    }

    public function testStringMapsToCancelled(): void
    {
        $this->assertSame(SessionStatus::Cancelled, $this->mapper->fromString('cancelled'));
    }

    public function testUnknownStringThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mapper->fromString('unknown');
    }
}
