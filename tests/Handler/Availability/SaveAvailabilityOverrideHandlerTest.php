<?php

declare(strict_types=1);

namespace Rez\Tests\Handler\Availability;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\UseCase\Availability\SaveAvailabilityOverride\SaveAvailabilityOverrideResponse;
use Rez\Application\UseCase\Availability\SaveAvailabilityOverride\SaveAvailabilityOverrideUseCaseInterface;
use Rez\Domain\Availability\AvailabilityOverride;
use Rez\Domain\Resource\ResourceId;
use Rez\Handler\Availability\SaveAvailabilityOverrideHandler;

class SaveAvailabilityOverrideHandlerTest extends TestCase
{
    private SaveAvailabilityOverrideUseCaseInterface&MockObject $useCase;
    private SaveAvailabilityOverrideHandler $handler;
    private ResourceId $resourceId;

    protected function setUp(): void
    {
        $this->useCase    = $this->createMock(SaveAvailabilityOverrideUseCaseInterface::class);
        $this->handler    = new SaveAvailabilityOverrideHandler($this->useCase);
        $this->resourceId = ResourceId::generate();
    }

    public function testHandleReturnsSerializedOverride(): void
    {
        $override = new AvailabilityOverride(
            $this->resourceId,
            new DateTimeImmutable('2024-06-08'),
            false,
        );

        $this->useCase
            ->method('execute')
            ->willReturn(new SaveAvailabilityOverrideResponse($override));

        $result = $this->handler->handle([
            'resource_id' => $this->resourceId->toString(),
            'date'        => '2024-06-08',
            'available'   => false,
        ]);

        $this->assertSame($this->resourceId->toString(), $result['resource_id']);
        $this->assertSame('2024-06-08', $result['date']);
        $this->assertFalse($result['available']);
    }
}
