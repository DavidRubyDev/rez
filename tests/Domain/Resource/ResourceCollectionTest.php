<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Resource;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceCollection;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;

class ResourceCollectionTest extends TestCase
{
    private function makeResource(string $name = 'Room A'): Resource
    {
        return new Resource(ResourceId::generate(), ResourceType::fromString('meeting-room'), $name, 10);
    }

    public function testEmptyCreatesEmptyCollection(): void
    {
        $collection = ResourceCollection::empty();

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(0, $collection->count());
    }

    public function testAddReturnsNewInstanceWithElement(): void
    {
        $collection = ResourceCollection::empty();
        $updated    = $collection->add($this->makeResource());

        $this->assertSame(1, $updated->count());
    }

    public function testOriginalUnchangedAfterAdd(): void
    {
        $collection = ResourceCollection::empty();
        $collection->add($this->makeResource());

        $this->assertTrue($collection->isEmpty());
    }

    public function testIsEmptyFalseWhenNotEmpty(): void
    {
        $collection = ResourceCollection::empty()->add($this->makeResource());

        $this->assertFalse($collection->isEmpty());
    }

    public function testFilterReturnsMatchingSubset(): void
    {
        $a = $this->makeResource('Room A');
        $b = $this->makeResource('Room B');

        $collection = ResourceCollection::empty()->add($a)->add($b);
        $filtered   = $collection->filter(fn (Resource $r) => $r->name() === 'Room A');

        $this->assertSame(1, $filtered->count());
        $this->assertSame('Room A', $filtered->toArray()[0]->name());
    }

    public function testFindByIdReturnsCorrectResource(): void
    {
        $resource   = $this->makeResource();
        $collection = ResourceCollection::empty()->add($resource);

        $found = $collection->findById($resource->id());

        $this->assertNotNull($found);
        $this->assertTrue($resource->id()->equals($found->id()));
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $collection = ResourceCollection::empty();

        $this->assertNull($collection->findById(ResourceId::generate()));
    }
}
