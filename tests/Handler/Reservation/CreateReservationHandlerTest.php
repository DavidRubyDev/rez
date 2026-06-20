<?php

declare(strict_types=1);

namespace Rez\Tests\Handler\Reservation;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationResponse;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationUseCase;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Handler\Reservation\CreateReservationHandler;

class CreateReservationHandlerTest extends TestCase
{
    private CreateReservationUseCase&MockObject $useCase;
    private CreateReservationHandler $handler;
    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->useCase = $this->createMock(CreateReservationUseCase::class);
        $this->handler = new CreateReservationHandler($this->useCase);

        $this->reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        );
    }

    public function testHandleReturnsSerializedReservation(): void
    {
        $this->useCase
            ->method('execute')
            ->willReturn(new CreateReservationResponse($this->reservation));

        $result = $this->handler->handle([
            'resource_ids' => [$this->reservation->resourceIds->toArray()[0]->toString()],
            'start'        => '2024-01-15 10:00:00',
            'end'          => '2024-01-15 11:00:00',
            'party'        => [
                'name'  => 'John Doe',
                'email' => 'john@example.com',
                'size'  => 2,
            ],
        ]);

        $this->assertSame($this->reservation->id->toString(), $result['id']);
        $this->assertSame('pending', $result['status']);
        $this->assertSame('2024-01-15 10:00:00', $result['start']);
        $this->assertSame('2024-01-15 11:00:00', $result['end']);
        $this->assertSame('John Doe', $result['party']['name']);
        $this->assertSame('john@example.com', $result['party']['email']);
        $this->assertSame(2, $result['party']['size']);
        $this->assertNull($result['party']['phone']);
    }

    public function testHandleMissingResourceIdsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler->handle([
            'start' => '2024-01-15 10:00:00',
            'end'   => '2024-01-15 11:00:00',
            'party' => ['name' => 'John Doe', 'email' => 'john@example.com', 'size' => 2],
        ]);
    }

    public function testHandleMissingPartyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler->handle([
            'resource_ids' => ['some-uuid'],
            'start'        => '2024-01-15 10:00:00',
            'end'          => '2024-01-15 11:00:00',
        ]);
    }
}
