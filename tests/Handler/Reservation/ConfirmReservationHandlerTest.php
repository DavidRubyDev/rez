<?php

declare(strict_types=1);

namespace Rez\Tests\Handler\Reservation;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\UseCase\Reservation\ConfirmReservation\ConfirmReservationResponse;
use Rez\Application\UseCase\Reservation\ConfirmReservation\ConfirmReservationUseCaseInterface;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Handler\Reservation\ConfirmReservationHandler;

class ConfirmReservationHandlerTest extends TestCase
{
    private ConfirmReservationUseCaseInterface&MockObject $useCase;
    private ConfirmReservationHandler $handler;
    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->useCase  = $this->createMock(ConfirmReservationUseCaseInterface::class);
        $this->handler  = new ConfirmReservationHandler($this->useCase);

        $this->reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        )->confirm();
    }

    public function testHandleReturnsSerializedConfirmedReservation(): void
    {
        $this->useCase
            ->method('execute')
            ->willReturn(new ConfirmReservationResponse($this->reservation));

        $result = $this->handler->handle(['id' => $this->reservation->id->toString()]);

        $this->assertSame($this->reservation->id->toString(), $result['id']);
        $this->assertSame('confirmed', $result['status']);
    }
}
