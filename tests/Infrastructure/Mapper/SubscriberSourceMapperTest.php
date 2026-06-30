<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Mapper;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Newsletter\SubscriberSource;
use Rez\Infrastructure\Mapper\SubscriberSourceMapper;

class SubscriberSourceMapperTest extends TestCase
{
    private SubscriberSourceMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new SubscriberSourceMapper();
    }

    public function testGuestMapsToString(): void
    {
        $this->assertSame('guest', $this->mapper->toString(SubscriberSource::Guest));
    }

    public function testRegisteredMapsToString(): void
    {
        $this->assertSame('registered', $this->mapper->toString(SubscriberSource::Registered));
    }

    public function testStringMapsToGuest(): void
    {
        $this->assertSame(SubscriberSource::Guest, $this->mapper->fromString('guest'));
    }

    public function testStringMapsToRegistered(): void
    {
        $this->assertSame(SubscriberSource::Registered, $this->mapper->fromString('registered'));
    }

    public function testAdminMapsToString(): void
    {
        $this->assertSame('admin', $this->mapper->toString(SubscriberSource::Admin));
    }

    public function testStringMapsToAdmin(): void
    {
        $this->assertSame(SubscriberSource::Admin, $this->mapper->fromString('admin'));
    }

    public function testUnknownStringThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mapper->fromString('unknown');
    }
}
