<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Session\ListSessions;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\Port\SessionRepositoryInterface;
use Rez\Application\UseCase\Session\ListSessions\ListSessionsRequest;
use Rez\Application\UseCase\Session\ListSessions\ListSessionsUseCase;
use Rez\Domain\Exception\ResourceNotFoundException;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;
use Rez\Domain\Session\Session;
use Rez\Domain\Session\SessionCollection;
use Rez\Domain\Session\SessionId;

class ListSessionsUseCaseTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private ResourceRepositoryInterface&MockObject $resourceRepository;
    private ListSessionsUseCase $useCase;
    private ResourceId $resourceId;
    private Resource $resource;

    protected function setUp(): void
    {
        $this->sessionRepository  = $this->createMock(SessionRepositoryInterface::class);
        $this->resourceRepository = $this->createMock(ResourceRepositoryInterface::class);
        $this->useCase            = new ListSessionsUseCase($this->sessionRepository, $this->resourceRepository);

        $this->resourceId = ResourceId::generate();
        $this->resource    = new Resource($this->resourceId, ResourceType::fromString('class'), 'Pilates', 20);
    }

    public function testThrowsResourceNotFoundExceptionWhenResourceMissing(): void
    {
        $this->resourceRepository->method('findById')->willThrowException(new ResourceNotFoundException());

        $this->expectException(ResourceNotFoundException::class);

        $this->useCase->execute(new ListSessionsRequest($this->resourceId));
    }

    public function testDatabaseExceptionPropagates(): void
    {
        $this->resourceRepository->method('findById')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to list sessions.');

        $this->useCase->execute(new ListSessionsRequest($this->resourceId));
    }

    public function testDelegatesToSessionRepository(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $session    = Session::create(SessionId::generate(), $this->resourceId, new DateTimeImmutable('2024-06-03 09:00:00'), 60, 10);
        $collection = SessionCollection::fromArray([$session]);

        $from = new DateTimeImmutable('2024-06-01');
        $to   = new DateTimeImmutable('2024-06-30');

        $this->sessionRepository
            ->expects($this->once())
            ->method('findForResource')
            ->with($this->resourceId, $from, $to)
            ->willReturn($collection);

        $response = $this->useCase->execute(new ListSessionsRequest($this->resourceId, $from, $to));

        $this->assertSame($collection, $response->sessions);
    }
}
