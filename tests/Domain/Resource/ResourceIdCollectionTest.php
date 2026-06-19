<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Resource;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;

class ResourceIdCollectionTest extends TestCase
{
    public function testEmptyCreatesEmptyCollection(): void
    {
        $collection = ResourceIdCollection::empty();

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(0, $collection->count());
    }

    public function testAddReturnsNewInstanceWithElement(): void
    {
        $id         = ResourceId::generate();
        $collection = ResourceIdCollection::empty()->add($id);

        $this->assertSame(1, $collection->count());
    }

    public function testOriginalUnchangedAfterAdd(): void
    {
        $collection = ResourceIdCollection::empty();
        $collection->add(ResourceId::generate());

        $this->assertTrue($collection->isEmpty());
    }

    public function testFromArrayCreatesCollection(): void
    {
        $ids        = [ResourceId::generate(), ResourceId::generate()];
        $collection = ResourceIdCollection::fromArray($ids);

        $this->assertSame(2, $collection->count());
    }

    public function testFromArrayThrowsWhenEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ResourceIdCollection::fromArray([]);
    }

    public function testContainsReturnsTrueForExistingId(): void
    {
        $id         = ResourceId::generate();
        $collection = ResourceIdCollection::empty()->add($id);

        $this->assertTrue($collection->contains($id));
    }

    public function testContainsReturnsFalseForMissingId(): void
    {
        $collection = ResourceIdCollection::empty()->add(ResourceId::generate());

        $this->assertFalse($collection->contains(ResourceId::generate()));
    }

    public function testToArrayReturnsAllIds(): void
    {
        $ids        = [ResourceId::generate(), ResourceId::generate()];
        $collection = ResourceIdCollection::fromArray($ids);

        $this->assertCount(2, $collection->toArray());
    }
}
