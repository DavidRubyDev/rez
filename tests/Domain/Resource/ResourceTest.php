<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Resource;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;

class ResourceTest extends TestCase
{
    private ResourceId $id;
    private ResourceType $type;

    protected function setUp(): void
    {
        $this->id   = ResourceId::generate();
        $this->type = ResourceType::fromString('meeting-room');
    }

    public function testValidConstruction(): void
    {
        $resource = new Resource($this->id, $this->type, 'Room A', 10);

        $this->assertTrue($this->id->equals($resource->getId()));
        $this->assertTrue($this->type->equals($resource->getType()));
        $this->assertSame('Room A', $resource->getName());
        $this->assertSame(10, $resource->getCapacity());
        $this->assertSame([], $resource->getAttributes());
    }

    public function testEmptyNameThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Resource($this->id, $this->type, '', 10);
    }

    public function testCapacityZeroThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Resource($this->id, $this->type, 'Room A', 0);
    }

    public function testWithAttributesReturnsNewInstance(): void
    {
        $resource = new Resource($this->id, $this->type, 'Room A', 10);
        $updated  = $resource->withAttributes(['projector' => true]);

        $this->assertSame(['projector' => true], $updated->getAttributes());
    }

    public function testOriginalUnchangedAfterWithAttributes(): void
    {
        $resource = new Resource($this->id, $this->type, 'Room A', 10);
        $resource->withAttributes(['projector' => true]);

        $this->assertSame([], $resource->getAttributes());
    }
}
