<?php

declare(strict_types=1);

namespace Rez\Tests\Handler\Reservation;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\UseCase\Reservation\ListReservations\ListReservationsResponse;
use Rez\Application\UseCase\Reservation\ListReservations\ListReservationsUseCase;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationCollection;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Handler\Reservation\ListReservationsHandler;

class ListReservationsHandlerTest extends TestCase
{
    private ListReservationsUseCase&MockObject $useCase;
    private ListReservationsHandler $handler;

    protected function setUp(): void
    {
        $this->useCase = $this->createMock(ListReservationsUseCase::class);
        $this->handler = new ListReservationsHandler($this->useCase);
    }

    private function makeReservation(): Reservation
    {
        return Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        );
    }

    public function testHandleReturnsSerializedList(): void
    {
        $collection = ReservationCollection::fromArray([$this->makeReservation(), $this->makeReservation()]);

        $this->useCase
            ->method('execute')
            ->willReturn(new ListReservationsResponse($collection));

        $result = $this->handler->handle([]);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('status', $result[0]);
    }

    public function testHandleWithNoFiltersPassesNulls(): void
    {
        $this->useCase
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(
                fn ($r) => $r->from === null && $r->to === null && $r->resourceId === null
            ))
            ->willReturn(new ListReservationsResponse(ReservationCollection::empty()));

        $this->handler->handle([]);
    }

    public function testHandleWithFiltersPassesThem(): void
    {
        $resourceId = ResourceId::generate();

        $this->useCase
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(
                fn ($r) => $r->from !== null && $r->to !== null && $r->resourceId !== null
            ))
            ->willReturn(new ListReservationsResponse(ReservationCollection::empty()));

        $this->handler->handle([
            'from'        => '2024-01-01',
            'to'          => '2024-01-31',
            'resource_id' => $resourceId->toString(),
        ]);
    }
}
