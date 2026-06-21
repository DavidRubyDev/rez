<?php

declare(strict_types=1);

namespace Rez\Tests\Handler\Reservation;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\UseCase\Reservation\MarkNoShow\MarkNoShowResponse;
use Rez\Application\UseCase\Reservation\MarkNoShow\MarkNoShowUseCaseInterface;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Handler\Reservation\MarkNoShowHandler;

class MarkNoShowHandlerTest extends TestCase
{
    private MarkNoShowUseCaseInterface&MockObject $useCase;
    private MarkNoShowHandler $handler;
    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->useCase  = $this->createMock(MarkNoShowUseCaseInterface::class);
        $this->handler  = new MarkNoShowHandler($this->useCase);

        $this->reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        )->confirm()->markNoShow();
    }

    public function testHandleReturnsSerializedNoShowReservation(): void
    {
        $this->useCase
            ->method('execute')
            ->willReturn(new MarkNoShowResponse($this->reservation));

        $result = $this->handler->handle(['id' => $this->reservation->id->toString()]);

        $this->assertSame($this->reservation->id->toString(), $result['id']);
        $this->assertSame('no_show', $result['status']);
    }
}
