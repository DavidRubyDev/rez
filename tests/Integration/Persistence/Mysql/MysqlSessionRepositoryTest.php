<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use DateTimeImmutable;
use Psr\Log\NullLogger;
use Rez\Domain\Exception\SessionNotFoundException;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Session\Session;
use Rez\Domain\Session\SessionId;
use Rez\Domain\Session\SessionStatus;
use Rez\Infrastructure\Mapper\SessionStatusMapper;
use Rez\Infrastructure\Persistence\Mysql\MysqlSessionRepository;

class MysqlSessionRepositoryTest extends MysqlIntegrationTestCase
{
    private MysqlSessionRepository $repository;
    private ResourceId $resourceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MysqlSessionRepository($this->pdo(), new SessionStatusMapper(), new NullLogger());
        $this->resourceId = $this->insertResource();
    }

    private function makeSession(?DateTimeImmutable $startTime = null, int $capacity = 10): Session
    {
        return Session::create(
            SessionId::generate(),
            $this->resourceId,
            $startTime ?? new DateTimeImmutable('2024-06-03 09:00:00'),
            60,
            $capacity,
        );
    }

    public function testSaveAndFindByIdRoundtrip(): void
    {
        $session = $this->makeSession();
        $this->repository->save($session);

        $found = $this->repository->findById($session->id);

        $this->assertTrue($session->id->equals($found->id));
        $this->assertTrue($session->resourceId->equals($found->resourceId));
        $this->assertSame($session->durationMinutes, $found->durationMinutes);
        $this->assertSame($session->capacity, $found->capacity);
        $this->assertSame(SessionStatus::Scheduled, $found->status);
    }

    public function testFindByIdMissingThrowsSessionNotFoundException(): void
    {
        $this->expectException(SessionNotFoundException::class);
        $this->repository->findById(SessionId::generate());
    }

    public function testSaveUpdatesExistingSession(): void
    {
        $session = $this->makeSession();
        $this->repository->save($session);

        $cancelled = $session->cancel();
        $this->repository->save($cancelled);

        $found = $this->repository->findById($session->id);

        $this->assertSame(SessionStatus::Cancelled, $found->status);
    }

    public function testFindForResourceReturnsOnlySessionsForThatResource(): void
    {
        $otherResourceId = $this->insertResource();
        $matching         = $this->makeSession();
        $unrelated        = Session::create(SessionId::generate(), $otherResourceId, new DateTimeImmutable('2024-06-03 09:00:00'), 60, 10);

        $this->repository->save($matching);
        $this->repository->save($unrelated);

        $result = $this->repository->findForResource($this->resourceId);

        $this->assertSame(1, $result->count());
        $this->assertTrue($result->toArray()[0]->id->equals($matching->id));
    }

    public function testFindForResourceFiltersByFromTo(): void
    {
        $inside  = $this->makeSession(new DateTimeImmutable('2024-06-03 09:00:00'));
        $outside = $this->makeSession(new DateTimeImmutable('2024-06-10 09:00:00'));

        $this->repository->save($inside);
        $this->repository->save($outside);

        $result = $this->repository->findForResource(
            $this->resourceId,
            new DateTimeImmutable('2024-06-01'),
            new DateTimeImmutable('2024-06-05'),
        );

        $this->assertSame(1, $result->count());
        $this->assertTrue($result->toArray()[0]->id->equals($inside->id));
    }
}
