<?php

declare(strict_types=1);

namespace Rez\Tests\Handler\Availability;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\UseCase\Availability\GetAvailability\GetAvailabilityResponse;
use Rez\Application\UseCase\Availability\GetAvailability\GetAvailabilityUseCaseInterface;
use Rez\Domain\Availability\AvailabilityWindow;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Handler\Availability\GetAvailabilityHandler;

class GetAvailabilityHandlerTest extends TestCase
{
    private GetAvailabilityUseCaseInterface&MockObject $useCase;
    private GetAvailabilityHandler $handler;
    private ResourceId $resourceId;

    protected function setUp(): void
    {
        $this->useCase    = $this->createMock(GetAvailabilityUseCaseInterface::class);
        $this->handler    = new GetAvailabilityHandler($this->useCase);
        $this->resourceId = ResourceId::generate();
    }

    public function testHandleReturnsSerializedWindow(): void
    {
        $date   = new DateTimeImmutable('2024-01-15');
        $slots  = [
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new TimeSlot(new DateTimeImmutable('2024-01-15 11:00:00'), new DateTimeImmutable('2024-01-15 12:00:00')),
        ];
        $window = new AvailabilityWindow($this->resourceId, $date, $slots);

        $this->useCase
            ->method('execute')
            ->willReturn(new GetAvailabilityResponse($window));

        $result = $this->handler->handle([
            'resource_id'           => $this->resourceId->toString(),
            'date'                  => '2024-01-15',
            'slot_duration_minutes' => 60,
        ]);

        $this->assertSame($this->resourceId->toString(), $result['resource_id']);
        $this->assertSame('2024-01-15', $result['date']);
        $slots = $result['slots'];
        $this->assertCount(2, $slots);
        $this->assertSame('2024-01-15 10:00:00', $slots[0]['start']);
        $this->assertSame('2024-01-15 11:00:00', $slots[0]['end']);
    }

    public function testHandleEmptyWindowReturnsEmptySlots(): void
    {
        $date   = new DateTimeImmutable('2024-01-15');
        $window = AvailabilityWindow::empty($this->resourceId, $date);

        $this->useCase
            ->method('execute')
            ->willReturn(new GetAvailabilityResponse($window));

        $result = $this->handler->handle([
            'resource_id'           => $this->resourceId->toString(),
            'date'                  => '2024-01-15',
            'slot_duration_minutes' => 60,
        ]);

        $this->assertSame([], $result['slots']);
    }

}
