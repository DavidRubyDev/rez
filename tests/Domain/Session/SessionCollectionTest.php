<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Session;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Session\Session;
use Rez\Domain\Session\SessionCollection;
use Rez\Domain\Session\SessionId;

class SessionCollectionTest extends TestCase
{
    private function makeSession(int $capacity = 10): Session
    {
        return Session::create(SessionId::generate(), ResourceId::generate(), new DateTimeImmutable('2024-06-03 09:00:00'), 60, $capacity);
    }

    public function testEmptyCreatesEmptyCollection(): void
    {
        $collection = SessionCollection::empty();

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(0, $collection->count());
    }

    public function testAddReturnsNewInstanceWithElement(): void
    {
        $collection = SessionCollection::empty();
        $updated    = $collection->add($this->makeSession());

        $this->assertSame(1, $updated->count());
    }

    public function testOriginalUnchangedAfterAdd(): void
    {
        $collection = SessionCollection::empty();
        $collection->add($this->makeSession());

        $this->assertTrue($collection->isEmpty());
    }

    public function testIsEmptyFalseWhenNotEmpty(): void
    {
        $collection = SessionCollection::empty()->add($this->makeSession());

        $this->assertFalse($collection->isEmpty());
    }

    public function testFilterReturnsMatchingSubset(): void
    {
        $a = $this->makeSession(5);
        $b = $this->makeSession(10);

        $collection = SessionCollection::empty()->add($a)->add($b);
        $filtered   = $collection->filter(fn (Session $s) => $s->capacity === 5);

        $this->assertSame(1, $filtered->count());
        $this->assertSame(5, $filtered->toArray()[0]->capacity);
    }

    public function testFindByIdReturnsCorrectSession(): void
    {
        $session    = $this->makeSession();
        $collection = SessionCollection::empty()->add($session);

        $found = $collection->findById($session->id);

        $this->assertNotNull($found);
        $this->assertTrue($session->id->equals($found->id));
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $collection = SessionCollection::empty();

        $this->assertNull($collection->findById(SessionId::generate()));
    }

    public function testFromArrayWrapsGivenSessions(): void
    {
        $session    = $this->makeSession();
        $collection = SessionCollection::fromArray([$session]);

        $this->assertSame(1, $collection->count());
    }
}
