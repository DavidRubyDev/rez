<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Session\ListSessions;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
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
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private ListSessionsUseCase $useCase;
    private ResourceId $resourceId;
    private Resource $resource;

    protected function setUp(): void
    {
        $this->sessionRepository     = $this->createMock(SessionRepositoryInterface::class);
        $this->resourceRepository    = $this->createMock(ResourceRepositoryInterface::class);
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->useCase               = new ListSessionsUseCase(
            $this->sessionRepository,
            $this->resourceRepository,
            $this->reservationRepository,
        );

        $this->resourceId = ResourceId::generate();
        $this->resource   = new Resource($this->resourceId, ResourceType::fromString('class'), 'Pilates', 20);
    }

    public function testThrowsResourceNotFoundExceptionWhenRequestedResourceMissing(): void
    {
        $this->resourceRepository->method('findById')->willThrowException(new ResourceNotFoundException());

        $this->expectException(ResourceNotFoundException::class);

        $this->useCase->execute(new ListSessionsRequest([$this->resourceId]));
    }

    public function testDatabaseExceptionPropagates(): void
    {
        $this->resourceRepository->method('findById')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to list sessions.');

        $this->useCase->execute(new ListSessionsRequest([$this->resourceId]));
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
            ->method('findForResources')
            ->with([$this->resourceId], $from, $to, false)
            ->willReturn($collection);

        $response = $this->useCase->execute(new ListSessionsRequest([$this->resourceId], $from, $to));

        $this->assertSame($collection, $response->sessions);
    }

    public function testListsEverySessionWhenNoResourceIsRequested(): void
    {
        $this->resourceRepository->expects($this->never())->method('findById');

        $collection = SessionCollection::fromArray([]);

        $this->sessionRepository
            ->expects($this->once())
            ->method('findForResources')
            ->with([], null, null, false)
            ->willReturn($collection);

        $response = $this->useCase->execute(new ListSessionsRequest());

        $this->assertSame($collection, $response->sessions);
    }

    public function testValidatesEveryRequestedResource(): void
    {
        $otherResourceId = ResourceId::generate();

        $this->resourceRepository->expects($this->exactly(2))->method('findById')->willReturn($this->resource);
        $this->sessionRepository->method('findForResources')->willReturn(SessionCollection::fromArray([]));

        $this->useCase->execute(new ListSessionsRequest([$this->resourceId, $otherResourceId]));
    }

    public function testPassesIncludeUnpublishedThrough(): void
    {
        $this->sessionRepository
            ->expects($this->once())
            ->method('findForResources')
            ->with([], null, null, true)
            ->willReturn(SessionCollection::fromArray([]));

        $this->useCase->execute(new ListSessionsRequest([], null, null, true));
    }

    public function testReturnsBookedCountPerSession(): void
    {
        $sessionId = SessionId::generate();
        $session   = Session::create($sessionId, $this->resourceId, new DateTimeImmutable('2024-06-03 09:00:00'), 60, 10);

        $this->sessionRepository->method('findForResources')->willReturn(SessionCollection::fromArray([$session]));

        $this->reservationRepository
            ->expects($this->once())
            ->method('countBookedBySessionIds')
            ->with([$sessionId->toString()])
            ->willReturn([$sessionId->toString() => 4]);

        $response = $this->useCase->execute(new ListSessionsRequest());

        $this->assertSame(4, $response->bookedCounts[$sessionId->toString()]);
    }

    public function testReportsZeroForASessionNobodyHasBooked(): void
    {
        $sessionId = SessionId::generate();
        $session   = Session::create($sessionId, $this->resourceId, new DateTimeImmutable('2024-06-03 09:00:00'), 60, 10);

        $this->sessionRepository->method('findForResources')->willReturn(SessionCollection::fromArray([$session]));
        $this->reservationRepository->method('countBookedBySessionIds')->willReturn([]);

        $response = $this->useCase->execute(new ListSessionsRequest());

        $this->assertSame(0, $response->bookedCounts[$sessionId->toString()]);
    }

    public function testSkipsTheCountQueryWhenThereAreNoSessions(): void
    {
        $this->sessionRepository->method('findForResources')->willReturn(SessionCollection::fromArray([]));

        $this->reservationRepository->expects($this->never())->method('countBookedBySessionIds');

        $response = $this->useCase->execute(new ListSessionsRequest());

        $this->assertSame([], $response->bookedCounts);
    }
}
