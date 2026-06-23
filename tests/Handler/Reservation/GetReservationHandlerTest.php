<?php

declare(strict_types=1);

namespace Rez\Tests\Handler\Reservation;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\UseCase\Reservation\GetReservation\GetReservationResponse;
use Rez\Application\UseCase\Reservation\GetReservation\GetReservationUseCaseInterface;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Handler\Reservation\GetReservationHandler;

class GetReservationHandlerTest extends TestCase
{
    private GetReservationUseCaseInterface&MockObject $useCase;
    private GetReservationHandler $handler;
    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->useCase = $this->createMock(GetReservationUseCaseInterface::class);
        $this->handler = new GetReservationHandler($this->useCase);

        $this->reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, '+1234567890'),
        );
    }

    public function testHandleReturnsSerializedReservation(): void
    {
        $this->useCase
            ->method('execute')
            ->willReturn(new GetReservationResponse($this->reservation));

        $result = $this->handler->handle(['id' => $this->reservation->id->toString()]);

        $this->assertSame($this->reservation->id->toString(), $result['id']);
        $this->assertSame('pending', $result['status']);
        $this->assertSame('2024-01-15 10:00:00', $result['start']);
        $this->assertSame('2024-01-15 11:00:00', $result['end']);
        $this->assertSame('+1234567890', $result['party']['phone']);
        $this->assertNull($result['party']['external_ref']);
    }

    public function testHandleIncludesExternalRefInParty(): void
    {
        $reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('Jane Smith', 'jane@example.com', 1, null, 'user-uuid-456'),
        );

        $this->useCase
            ->method('execute')
            ->willReturn(new GetReservationResponse($reservation));

        $result = $this->handler->handle(['id' => $reservation->id->toString()]);

        $this->assertSame('user-uuid-456', $result['party']['external_ref']);
    }

}
