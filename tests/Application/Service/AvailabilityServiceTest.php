<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Service;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Service\AvailabilityService;
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

class AvailabilityServiceTest extends TestCase
{
    private AvailabilityRepositoryInterface&MockObject $availabilityRepository;
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private AvailabilityService $service;
    private ResourceId $resourceId;
    // 2024-01-15 is a Monday
    private DateTimeImmutable $date;
    private AvailabilityRule $mondayRule;

    protected function setUp(): void
    {
        $this->availabilityRepository = $this->createMock(AvailabilityRepositoryInterface::class);
        $this->reservationRepository  = $this->createMock(ReservationRepositoryInterface::class);
        $this->service                = new AvailabilityService($this->availabilityRepository, $this->reservationRepository);

        $this->resourceId  = ResourceId::generate();
        $this->date        = new DateTimeImmutable('2024-01-15');
        $this->mondayRule  = new AvailabilityRule($this->resourceId, DayOfWeek::Monday, '10:00', '12:00');
    }

    // --- isSlotAvailable ---

    public function testIsSlotAvailableReturnsFalseWhenNoRuleForDate(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([]);

        $slot = new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00'));

        $this->assertFalse($this->service->isSlotAvailable($this->resourceId, $slot));
    }

    public function testIsSlotAvailableReturnsFalseWhenOverrideBlocks(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([
            new AvailabilityOverride($this->resourceId, $this->date, false),
        ]);

        $slot = new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00'));

        $this->assertFalse($this->service->isSlotAvailable($this->resourceId, $slot));
    }

    public function testIsSlotAvailableReturnsFalseWhenConflictExists(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);

        $slot        = new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00'));
        $party       = new Party('John Doe', 'john@example.com', 2, null);
        $reservation = Reservation::create(ReservationId::generate(), ResourceIdCollection::fromArray([$this->resourceId]), $slot, $party);

        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(
            ReservationCollection::fromArray([$reservation])
        );

        $this->assertFalse($this->service->isSlotAvailable($this->resourceId, $slot));
    }

    public function testIsSlotAvailableReturnsTrueWhenAllClear(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        $slot = new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00'));

        $this->assertTrue($this->service->isSlotAvailable($this->resourceId, $slot));
    }

    // --- getAvailableSlots ---

    public function testGetAvailableSlotsReturnsEmptyWindowWhenNoRuleForDate(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([]);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        $window = $this->service->getAvailableSlots($this->resourceId, $this->date, 60);

        $this->assertTrue($window->isEmpty());
    }

    public function testGetAvailableSlotsReturnsEmptyWindowWhenOverrideBlocks(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([
            new AvailabilityOverride($this->resourceId, $this->date, false),
        ]);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        $window = $this->service->getAvailableSlots($this->resourceId, $this->date, 60);

        $this->assertTrue($window->isEmpty());
    }

    public function testGetAvailableSlotsReturnsAllSlotsWhenNoReservations(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        // 10:00–12:00 with 60-minute slots = 2 slots
        $window = $this->service->getAvailableSlots($this->resourceId, $this->date, 60);

        $this->assertSame(2, $window->count());
    }

    public function testGetAvailableSlotsReturnsEmptyWindowWhenAllSlotsTaken(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);

        $party        = new Party('John Doe', 'john@example.com', 2, null);
        $slot1        = new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00'));
        $slot2        = new TimeSlot(new DateTimeImmutable('2024-01-15 11:00:00'), new DateTimeImmutable('2024-01-15 12:00:00'));
        $reservations = ReservationCollection::fromArray([
            Reservation::create(ReservationId::generate(), ResourceIdCollection::fromArray([$this->resourceId]), $slot1, $party),
            Reservation::create(ReservationId::generate(), ResourceIdCollection::fromArray([$this->resourceId]), $slot2, $party),
        ]);

        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn($reservations);

        $window = $this->service->getAvailableSlots($this->resourceId, $this->date, 60);

        $this->assertTrue($window->isEmpty());
    }

    public function testGetAvailableSlotsRemovesOnlyOverlappingSlot(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);

        $party       = new Party('John Doe', 'john@example.com', 2, null);
        $takenSlot   = new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00'));
        $reservation = Reservation::create(ReservationId::generate(), ResourceIdCollection::fromArray([$this->resourceId]), $takenSlot, $party);

        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(
            ReservationCollection::fromArray([$reservation])
        );

        $window = $this->service->getAvailableSlots($this->resourceId, $this->date, 60);

        $this->assertSame(1, $window->count());
        $this->assertSame('2024-01-15 11:00:00', $window->slots[0]->start->format('Y-m-d H:i:s'));
    }

    public function testGetAvailableSlotsExcludesSlotThatWouldExceedCloseTime(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        // 90-minute slots in a 2-hour window = only 1 fits
        $window = $this->service->getAvailableSlots($this->resourceId, $this->date, 90);

        $this->assertSame(1, $window->count());
    }

    public function testGetAvailableSlotsCorrectlyDividesWindow(): void
    {
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$this->mondayRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        // 30-minute slots in a 2-hour window = 4 slots
        $window = $this->service->getAvailableSlots($this->resourceId, $this->date, 30);

        $this->assertSame(4, $window->count());
    }

    public function testGetAvailableSlotsReturnsEmptyWhenRuleValidUntilIsInPast(): void
    {
        $expiredRule = new AvailabilityRule(
            $this->resourceId,
            DayOfWeek::Monday,
            '10:00',
            '12:00',
            validUntil: new DateTimeImmutable('2024-01-14', new \DateTimeZone('UTC')),
        );

        $this->availabilityRepository->method('findRulesForResource')->willReturn([$expiredRule]);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        $window = $this->service->getAvailableSlots($this->resourceId, $this->date, 60);

        $this->assertTrue($window->isEmpty());
    }

    public function testGetAvailableSlotsReturnsEmptyWhenRuleValidFromIsInFuture(): void
    {
        $futureRule = new AvailabilityRule(
            $this->resourceId,
            DayOfWeek::Monday,
            '10:00',
            '12:00',
            validFrom: new DateTimeImmutable('2024-01-16', new \DateTimeZone('UTC')),
        );

        $this->availabilityRepository->method('findRulesForResource')->willReturn([$futureRule]);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        $window = $this->service->getAvailableSlots($this->resourceId, $this->date, 60);

        $this->assertTrue($window->isEmpty());
    }

    public function testGetAvailableSlotsUsesRuleWhenDateIsWithinBounds(): void
    {
        $boundedRule = new AvailabilityRule(
            $this->resourceId,
            DayOfWeek::Monday,
            '10:00',
            '12:00',
            validFrom:  new DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')),
            validUntil: new DateTimeImmutable('2024-03-31', new \DateTimeZone('UTC')),
        );

        $this->availabilityRepository->method('findRulesForResource')->willReturn([$boundedRule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);
        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn(ReservationCollection::empty());

        $window = $this->service->getAvailableSlots($this->resourceId, $this->date, 60);

        $this->assertSame(2, $window->count());
    }

    public function testGetAvailableSlotsAdjacentReservationsDoNotBlockEachOther(): void
    {
        $rule = new AvailabilityRule($this->resourceId, DayOfWeek::Monday, '10:00', '14:00');
        $this->availabilityRepository->method('findRulesForResource')->willReturn([$rule]);
        $this->availabilityRepository->method('findOverridesForResource')->willReturn([]);

        $party        = new Party('John Doe', 'john@example.com', 2, null);
        $slot1        = new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00'));
        $slot2        = new TimeSlot(new DateTimeImmutable('2024-01-15 12:00:00'), new DateTimeImmutable('2024-01-15 13:00:00'));
        $reservations = ReservationCollection::fromArray([
            Reservation::create(ReservationId::generate(), ResourceIdCollection::fromArray([$this->resourceId]), $slot1, $party),
            Reservation::create(ReservationId::generate(), ResourceIdCollection::fromArray([$this->resourceId]), $slot2, $party),
        ]);

        $this->reservationRepository->method('findByTimeSlotAndResource')->willReturn($reservations);

        $window = $this->service->getAvailableSlots($this->resourceId, $this->date, 60);

        $this->assertSame(2, $window->count());
    }
}
