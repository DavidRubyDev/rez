<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Reservation\ListReservations;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\UseCase\Reservation\ListReservations\ListReservationsRequest;
use Rez\Application\UseCase\Reservation\ListReservations\ListReservationsUseCase;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationCollection;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;

class ListReservationsUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private ListReservationsUseCase $useCase;
    private ResourceId $resourceId;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->useCase               = new ListReservationsUseCase($this->reservationRepository);
        $this->resourceId            = ResourceId::generate();
    }

    private function makeReservation(ResourceId $resourceId): Reservation
    {
        return Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([$resourceId]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        );
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->reservationRepository
            ->method('findPage')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to list reservations.');

        $this->useCase->execute(new ListReservationsRequest());
    }

    public function testReturnsAllAndTotalWhenNoFilters(): void
    {
        $collection = ReservationCollection::fromArray([
            $this->makeReservation($this->resourceId),
            $this->makeReservation(ResourceId::generate()),
        ]);

        $this->reservationRepository->method('findPage')->willReturn($collection);
        $this->reservationRepository->method('countPage')->willReturn(2);

        $response = $this->useCase->execute(new ListReservationsRequest());

        $this->assertSame(2, $response->reservations->count());
        $this->assertSame(2, $response->total);
    }

    public function testPassesFiltersThroughToRepository(): void
    {
        $from   = new DateTimeImmutable('2024-01-01');
        $to     = new DateTimeImmutable('2024-01-31');
        $status = ReservationStatus::Confirmed;

        $this->reservationRepository
            ->expects($this->once())
            ->method('findPage')
            ->with($from, $to, $this->resourceId, $status, 'jane', null, null, null, null)
            ->willReturn(ReservationCollection::empty());

        $this->reservationRepository
            ->expects($this->once())
            ->method('countPage')
            ->with($from, $to, $this->resourceId, $status, 'jane')
            ->willReturn(0);

        $this->useCase->execute(new ListReservationsRequest(
            from: $from,
            to: $to,
            resourceId: $this->resourceId,
            status: $status,
            search: 'jane',
        ));
    }

    public function testPassesPaginationAndSortThroughToRepository(): void
    {
        $this->reservationRepository
            ->expects($this->once())
            ->method('findPage')
            ->with(null, null, null, null, null, 10, 20, 'start', 'desc')
            ->willReturn(ReservationCollection::empty());

        $this->reservationRepository->method('countPage')->willReturn(0);

        $this->useCase->execute(new ListReservationsRequest(
            offset: 10,
            limit: 20,
            sortBy: 'start',
            sortDir: 'desc',
        ));
    }

    public function testInvalidSortByThrowsInvalidArgumentException(): void
    {
        $this->reservationRepository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListReservationsRequest(sortBy: 'not_a_column'));
    }

    public function testInvalidLimitThrowsInvalidArgumentException(): void
    {
        $this->reservationRepository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListReservationsRequest(limit: 0));
    }

    public function testNegativeOffsetThrowsInvalidArgumentException(): void
    {
        $this->reservationRepository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListReservationsRequest(offset: -1));
    }

    public function testInvalidSortDirThrowsInvalidArgumentException(): void
    {
        $this->reservationRepository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListReservationsRequest(sortDir: 'sideways'));
    }
}
