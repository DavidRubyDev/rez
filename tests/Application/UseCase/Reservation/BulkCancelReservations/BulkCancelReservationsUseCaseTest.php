<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Reservation\BulkCancelReservations;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\UseCase\Reservation\BulkCancelReservations\BulkCancelReservationsRequest;
use Rez\Application\UseCase\Reservation\BulkCancelReservations\BulkCancelReservationsUseCase;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;

class BulkCancelReservationsUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private BulkCancelReservationsUseCase $useCase;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->useCase               = new BulkCancelReservationsUseCase($this->reservationRepository);
    }

    private function makeReservation(): Reservation
    {
        return Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(
                new DateTimeImmutable('2024-01-15 10:00:00'),
                new DateTimeImmutable('2024-01-15 11:00:00'),
            ),
            new Party('Jane Doe', 'jane@example.com', 2, null),
        );
    }

    public function testEmptyIdsReturnsEmptyArrays(): void
    {
        $response = $this->useCase->execute(new BulkCancelReservationsRequest([]));

        $this->assertSame([], $response->cancelled);
        $this->assertSame([], $response->skipped);
    }

    public function testCancelsReservationsAndReturnsCancelledArray(): void
    {
        $r1 = $this->makeReservation();
        $r2 = $this->makeReservation();

        $this->reservationRepository
            ->method('findById')
            ->willReturnOnConsecutiveCalls($r1, $r2);

        $response = $this->useCase->execute(new BulkCancelReservationsRequest([
            $r1->id,
            $r2->id,
        ]));

        $this->assertCount(2, $response->cancelled);
        $this->assertSame([], $response->skipped);
    }

    public function testCancelledArrayContainsReservationObjects(): void
    {
        $r1 = $this->makeReservation();

        $this->reservationRepository->method('findById')->willReturn($r1);

        $response = $this->useCase->execute(new BulkCancelReservationsRequest([$r1->id]));

        $this->assertInstanceOf(Reservation::class, $response->cancelled[0]);
        $this->assertTrue($r1->id->equals($response->cancelled[0]->id));
    }

    public function testSkipsNotFoundReservation(): void
    {
        $id = ReservationId::generate();

        $this->reservationRepository
            ->method('findById')
            ->willThrowException(new ReservationNotFoundException());

        $response = $this->useCase->execute(new BulkCancelReservationsRequest([$id]));

        $this->assertSame([], $response->cancelled);
        $this->assertCount(1, $response->skipped);
        $this->assertTrue($id->equals($response->skipped[0]));
    }

    public function testAlreadyCancelledReservationCountsAsCancelled(): void
    {
        $reservation = $this->makeReservation()->cancel();

        $this->reservationRepository->method('findById')->willReturn($reservation);

        $response = $this->useCase->execute(new BulkCancelReservationsRequest([
            $reservation->id,
        ]));

        $this->assertCount(1, $response->cancelled);
        $this->assertSame([], $response->skipped);
    }

    public function testMixedResultsReturnCorrectArrays(): void
    {
        $active    = $this->makeReservation();
        $alreadyCancelled = $this->makeReservation()->cancel();
        $notFound  = ReservationId::generate();

        $this->reservationRepository
            ->method('findById')
            ->willReturnCallback(function (ReservationId $id) use ($active, $alreadyCancelled): Reservation {
                if ($id->equals($active->id)) {
                    return $active;
                }
                if ($id->equals($alreadyCancelled->id)) {
                    return $alreadyCancelled;
                }
                throw new ReservationNotFoundException();
            });

        $response = $this->useCase->execute(new BulkCancelReservationsRequest([
            $active->id,
            $alreadyCancelled->id,
            $notFound,
        ]));

        $this->assertCount(2, $response->cancelled);
        $this->assertCount(1, $response->skipped);
    }

    public function testDatabaseExceptionOnFindPropagates(): void
    {
        $this->reservationRepository
            ->method('findById')
            ->willThrowException(new DatabaseException('connection lost'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load reservation.');

        $this->useCase->execute(new BulkCancelReservationsRequest([
            ReservationId::generate(),
        ]));
    }

    public function testDatabaseExceptionOnSavePropagates(): void
    {
        $reservation = $this->makeReservation();

        $this->reservationRepository->method('findById')->willReturn($reservation);
        $this->reservationRepository
            ->method('save')
            ->willThrowException(new DatabaseException('disk full'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to save reservation.');

        $this->useCase->execute(new BulkCancelReservationsRequest([$reservation->id]));
    }
}
