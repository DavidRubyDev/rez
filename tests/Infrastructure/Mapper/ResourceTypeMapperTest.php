<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Mapper;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Resource\ResourceType;
use Rez\Infrastructure\Mapper\ResourceTypeMapper;

class ResourceTypeMapperTest extends TestCase
{
    private ResourceTypeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ResourceTypeMapper();
    }

    public function testToStringReturnsSlug(): void
    {
        $type = ResourceType::fromString('meeting-room');

        $this->assertSame('meeting-room', $this->mapper->toString($type));
    }

    public function testFromStringReturnsEquivalentResourceType(): void
    {
        $type = $this->mapper->fromString('meeting-room');

        $this->assertTrue($type->equals(ResourceType::fromString('meeting-room')));
    }

    public function testRoundtrip(): void
    {
        $original = ResourceType::fromString('desk');

        $this->assertTrue($original->equals($this->mapper->fromString($this->mapper->toString($original))));
    }

    public function testInvalidStringThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mapper->fromString('Invalid Type!');
    }
}
