<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Availability\GetAvailability;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\UseCase\Availability\GetAvailability\GetAvailabilityRequest;
use Rez\Application\UseCase\Availability\GetAvailability\GetAvailabilityUseCase;
use Rez\Domain\Availability\AvailabilityOverride;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Availability\DayOfWeek;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationCollection;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;

class GetAvailabilityUseCaseTest extends TestCase
{
    private AvailabilityRepositoryInterface&MockObject $availabilityRepository;
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private GetAvailabilityUseCase $useCase;
    private ResourceId $resourceId;
    // 2024-01-15 is a Monday
    private DateTimeImmutable $date;
    private AvailabilityRule $mondayRule;

    protected function setUp(): void
    {
        $this->availabilityRepository = $this->createMock(AvailabilityRepositoryInterface::class);
        $this->reservationRepository  = $this->createMock(ReservationRepositoryInterface::class);
        $this->useCase                = new GetAvailabilityUseCase($this->availabilityRepository, $this->reservationRepository);

        $this->resourceId = ResourceId::generate();
        $this->date       = new DateTimeImmutable('2024-01-15');
        $this->mondayRule = new AvailabilityRule($this->resourceId, DayOfWeek::Monday, '10:00', '12:00');
    }

    public function testNoRuleForDateReturnsEmptyWindow(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([]);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        $response = $this->useCase->execute(new GetAvailabilityRequest($this->resourceId, $this->date, 60));

        $this->assertTrue($response->window->isEmpty());
    }

    public function testOverrideWithAvailableFalseReturnsEmptyWindow(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([
            new AvailabilityOverride($this->resourceId, $this->date, false),
        ]);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        $response = $this->useCase->execute(new GetAvailabilityRequest($this->resourceId, $this->date, 60));

        $this->assertTrue($response->window->isEmpty());
    }

    public function testNoReservationsReturnsAllSlots(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        $response = $this->useCase->execute(new GetAvailabilityRequest($this->resourceId, $this->date, 60));

        // 10:00–12:00 with 60-minute slots = 2 slots
        $this->assertSame(2, $response->window->count());
    }

    public function testAllSlotsTakenReturnsEmptyWindow(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);

        $party = new Party('John Doe', 'john@example.com', 2, null);
        $slot1 = new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00'));
        $slot2 = new TimeSlot(new DateTimeImmutable('2024-01-15 11:00:00'), new DateTimeImmutable('2024-01-15 12:00:00'));

        $reservations = ReservationCollection::fromArray([
            Reservation::create(ReservationId::generate(), ResourceIdCollection::fromArray([$this->resourceId]), $slot1, $party),
            Reservation::create(ReservationId::generate(), ResourceIdCollection::fromArray([$this->resourceId]), $slot2, $party),
        ]);

        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn($reservations);

        $response = $this->useCase->execute(new GetAvailabilityRequest($this->resourceId, $this->date, 60));

        $this->assertTrue($response->window->isEmpty());
    }

    public function testOneReservationRemovesOnlyOverlappingSlot(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);

        $party      = new Party('John Doe', 'john@example.com', 2, null);
        $takenSlot  = new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00'));
        $reservation = Reservation::create(ReservationId::generate(), ResourceIdCollection::fromArray([$this->resourceId]), $takenSlot, $party);

        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(
            ReservationCollection::fromArray([$reservation])
        );

        $response = $this->useCase->execute(new GetAvailabilityRequest($this->resourceId, $this->date, 60));

        $this->assertSame(1, $response->window->count());
        $this->assertSame('2024-01-15 11:00:00', $response->window->slots()[0]->start()->format('Y-m-d H:i:s'));
    }

    public function testSlotThatWouldExceedCloseTimeIsNotIncluded(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        // 90-minute slots in a 2-hour window = only 1 fits (10:00–11:30), not 11:30–13:00
        $response = $this->useCase->execute(new GetAvailabilityRequest($this->resourceId, $this->date, 90));

        $this->assertSame(1, $response->window->count());
    }

    public function testSlotDurationCorrectlyDividesWindow(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        // 30-minute slots in a 2-hour window = 4 slots
        $response = $this->useCase->execute(new GetAvailabilityRequest($this->resourceId, $this->date, 30));

        $this->assertSame(4, $response->window->count());
    }

    public function testAdjacentReservationsDoNotBlockEachOther(): void
    {
        // Rule: 10:00–14:00, slots: 60 min = 4 slots
        $rule = new AvailabilityRule($this->resourceId, DayOfWeek::Monday, '10:00', '14:00');
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$rule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);

        $party = new Party('John Doe', 'john@example.com', 2, null);
        // Reserve 10:00–11:00 and 12:00–13:00, leaving 11:00–12:00 and 13:00–14:00 free
        $slot1 = new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00'));
        $slot2 = new TimeSlot(new DateTimeImmutable('2024-01-15 12:00:00'), new DateTimeImmutable('2024-01-15 13:00:00'));

        $reservations = ReservationCollection::fromArray([
            Reservation::create(ReservationId::generate(), ResourceIdCollection::fromArray([$this->resourceId]), $slot1, $party),
            Reservation::create(ReservationId::generate(), ResourceIdCollection::fromArray([$this->resourceId]), $slot2, $party),
        ]);

        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn($reservations);

        $response = $this->useCase->execute(new GetAvailabilityRequest($this->resourceId, $this->date, 60));

        $this->assertSame(2, $response->window->count());
    }
}
