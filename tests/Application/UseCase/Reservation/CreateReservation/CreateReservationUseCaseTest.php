<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Reservation\CreateReservation;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationRequest;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationUseCase;
use Rez\Domain\Availability\AvailabilityOverride;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Availability\DayOfWeek;
use Rez\Domain\Exception\ConflictException;
use Rez\Domain\Exception\ResourceNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationCollection;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Domain\Resource\ResourceType;

class CreateReservationUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private ResourceRepositoryInterface&MockObject $resourceRepository;
    private AvailabilityRepositoryInterface&MockObject $availabilityRepository;
    private CreateReservationUseCase $useCase;
    private ResourceId $resourceId;
    private Resource $resource;
    private Party $party;
    // 2024-01-15 is a Monday
    private AvailabilityRule $mondayRule;

    protected function setUp(): void
    {
        $this->reservationRepository  = $this->createMock(ReservationRepositoryInterface::class);
        $this->resourceRepository     = $this->createMock(ResourceRepositoryInterface::class);
        $this->availabilityRepository = $this->createMock(AvailabilityRepositoryInterface::class);
        $this->useCase                = new CreateReservationUseCase(
            $this->reservationRepository,
            $this->resourceRepository,
            $this->availabilityRepository,
        );

        $this->resourceId  = ResourceId::generate();
        $this->resource    = new Resource($this->resourceId, ResourceType::fromString('table'), 'Table 1', 4);
        $this->party       = new Party('John Doe', 'john@example.com', 2, null);
        $this->mondayRule  = new AvailabilityRule($this->resourceId, DayOfWeek::Monday, '09:00', '17:00');
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

    public function testNoAvailabilityRuleForDateThrowsConflictException(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityRepository->method('findRulesForResource')->willReturn([]);

        $this->expectException(ConflictException::class);

        $this->useCase->execute(new CreateReservationRequest(
            [$this->resourceId],
            new DateTimeImmutable('2024-01-15 10:00:00'),
            new DateTimeImmutable('2024-01-15 11:00:00'),
            $this->party,
        ));
    }

    public function testAvailabilityOverrideBlockedThrowsConflictException(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([
            new AvailabilityOverride($this->resourceId, new DateTimeImmutable('2024-01-15'), false),
        ]);

        $this->expectException(ConflictException::class);

        $this->useCase->execute(new CreateReservationRequest(
            [$this->resourceId],
            new DateTimeImmutable('2024-01-15 10:00:00'),
            new DateTimeImmutable('2024-01-15 11:00:00'),
            $this->party,
        ));
    }

    public function testConflictingReservationThrowsConflictException(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);

        $existingReservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([$this->resourceId]),
            new TimeSlot(
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
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);
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
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);
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
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);
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
