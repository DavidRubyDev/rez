<?php

declare(strict_types=1);

namespace Rez\Tests\Handler\Reservation;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\UseCase\Reservation\CancelReservation\CancelReservationResponse;
use Rez\Application\UseCase\Reservation\CancelReservation\CancelReservationUseCase;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Handler\Reservation\CancelReservationHandler;

class CancelReservationHandlerTest extends TestCase
{
    private CancelReservationUseCase&MockObject $useCase;
    private CancelReservationHandler $handler;
    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->useCase = $this->createMock(CancelReservationUseCase::class);
        $this->handler = new CancelReservationHandler($this->useCase);

        $this->reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        )->cancel();
    }

    public function testHandleReturnsSerializedCancelledReservation(): void
    {
        $this->useCase
            ->method('execute')
            ->willReturn(new CancelReservationResponse($this->reservation));

        $result = $this->handler->handle(['id' => $this->reservation->id->toString()]);

        $this->assertSame($this->reservation->id->toString(), $result['id']);
        $this->assertSame('cancelled', $result['status']);
    }

    public function testHandleMissingIdThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler->handle([]);
    }
}
