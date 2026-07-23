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

        $this->assertTrue($this->id->equals($resource->id));
        $this->assertTrue($this->type->equals($resource->type));
        $this->assertSame('Room A', $resource->name);
        $this->assertSame(10, $resource->capacity);
        $this->assertSame([], $resource->attributes);
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

        $this->assertSame(['projector' => true], $updated->attributes);
    }

    public function testOriginalUnchangedAfterWithAttributes(): void
    {
        $resource = new Resource($this->id, $this->type, 'Room A', 10);
        $resource->withAttributes(['projector' => true]);

        $this->assertSame([], $resource->attributes);
    }

    public function testActiveDefaultsToTrue(): void
    {
        $resource = new Resource($this->id, $this->type, 'Room A', 10);

        $this->assertTrue($resource->active);
    }

    public function testDeactivateReturnsNewInstanceWithActiveFalse(): void
    {
        $resource   = new Resource($this->id, $this->type, 'Room A', 10);
        $deactivated = $resource->deactivate();

        $this->assertFalse($deactivated->active);
    }

    public function testDeactivateDoesNotMutateOriginal(): void
    {
        $resource = new Resource($this->id, $this->type, 'Room A', 10);
        $resource->deactivate();

        $this->assertTrue($resource->active);
    }

    public function testDefaultDurationMinutesDefaultsToNull(): void
    {
        $resource = new Resource($this->id, $this->type, 'Room A', 10);

        $this->assertNull($resource->defaultDurationMinutes);
    }

    public function testDefaultDurationMinutesIsStoredAndReturned(): void
    {
        $resource = new Resource($this->id, $this->type, 'Pilates', 20, defaultDurationMinutes: 45);

        $this->assertSame(45, $resource->defaultDurationMinutes);
    }
}
