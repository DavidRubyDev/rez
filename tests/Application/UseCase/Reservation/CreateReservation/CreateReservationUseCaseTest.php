<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Reservation\CreateReservation;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationRequest;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationUseCase;
use Rez\Domain\Exception\ConflictException;
use Rez\Domain\Exception\ResourceNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationCollection;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Domain\Resource\ResourceType;

class CreateReservationUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private ResourceRepositoryInterface&MockObject $resourceRepository;
    private CreateReservationUseCase $useCase;
    private ResourceId $resourceId;
    private Resource $resource;
    private Party $party;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->resourceRepository    = $this->createMock(ResourceRepositoryInterface::class);
        $this->useCase               = new CreateReservationUseCase($this->reservationRepository, $this->resourceRepository);

        $this->resourceId = ResourceId::generate();
        $this->resource   = new Resource($this->resourceId, ResourceType::fromString('table'), 'Table 1', 4);
        $this->party      = new Party('John Doe', 'john@example.com', 2, null);
    }

    public function testResourceNotFoundThrowsResourceNotFoundException(): void
    {
        $this->resourceRepository
            ->method('findById')
            ->willThrowException(new ResourceNotFoundException());

        $this->expectException(ResourceNotFoundException::class);

        $this->useCase->execute(new CreateReservationRequest(
            [$this->resourceId],
            new DateTimeImmutable('2024-01-15 10:00:00'),
            new DateTimeImmutable('2024-01-15 11:00:00'),
            $this->party,
        ));
    }

    public function testInvalidTimeSlotThrowsInvalidTimeSlotException(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $this->expectException(\Rez\Domain\Exception\InvalidTimeSlotException::class);

        $this->useCase->execute(new CreateReservationRequest(
            [$this->resourceId],
            new DateTimeImmutable('2024-01-15 11:00:00'),
            new DateTimeImmutable('2024-01-15 10:00:00'),
            $this->party,
        ));
    }

    public function testConflictingReservationThrowsConflictException(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $existingReservation = Reservation::create(
            \Rez\Domain\Reservation\ReservationId::generate(),
            ResourceIdCollection::fromArray([$this->resourceId]),
            new \Rez\Domain\Reservation\TimeSlot(
                new DateTimeImmutable('2024-01-15 10:00:00'),
                new DateTimeImmutable('2024-01-15 11:00:00'),
            ),
            $this->party,
        );

        $this->reservationRepository
            ->method('findByTimeSlotAndResource')
            ->willReturn(ReservationCollection::fromArray([$existingReservation]));

        $this->expectException(ConflictException::class);

        $this->useCase->execute(new CreateReservationRequest(
            [$this->resourceId],
            new DateTimeImmutable('2024-01-15 10:00:00'),
            new DateTimeImmutable('2024-01-15 11:00:00'),
            $this->party,
        ));
    }

    public function testSuccessSaveCalledOnce(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        $this->reservationRepository->expects($this->once())->method('save');

        $this->useCase->execute(new CreateReservationRequest(
            [$this->resourceId],
            new DateTimeImmutable('2024-01-15 10:00:00'),
            new DateTimeImmutable('2024-01-15 11:00:00'),
            $this->party,
        ));
    }

    public function testSuccessReturnsPendingReservation(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        $response = $this->useCase->execute(new CreateReservationRequest(
            [$this->resourceId],
            new DateTimeImmutable('2024-01-15 10:00:00'),
            new DateTimeImmutable('2024-01-15 11:00:00'),
            $this->party,
        ));

        $this->assertSame(ReservationStatus::Pending, $response->reservation->status());
    }

    public function testSuccessReservationHasCorrectResourceId(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        $response = $this->useCase->execute(new CreateReservationRequest(
            [$this->resourceId],
            new DateTimeImmutable('2024-01-15 10:00:00'),
            new DateTimeImmutable('2024-01-15 11:00:00'),
            $this->party,
        ));

        $this->assertTrue($response->reservation->resourceIds()->contains($this->resourceId));
    }
}
