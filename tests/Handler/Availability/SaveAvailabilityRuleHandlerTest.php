<?php

declare(strict_types=1);

namespace Rez\Tests\Handler\Availability;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\UseCase\Availability\SaveAvailabilityRule\SaveAvailabilityRuleResponse;
use Rez\Application\UseCase\Availability\SaveAvailabilityRule\SaveAvailabilityRuleUseCaseInterface;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Availability\DayOfWeek;
use Rez\Domain\Resource\ResourceId;
use Rez\Handler\Availability\SaveAvailabilityRuleHandler;

class SaveAvailabilityRuleHandlerTest extends TestCase
{
    private SaveAvailabilityRuleUseCaseInterface&MockObject $useCase;
    private SaveAvailabilityRuleHandler $handler;
    private ResourceId $resourceId;

    protected function setUp(): void
    {
        $this->useCase    = $this->createMock(SaveAvailabilityRuleUseCaseInterface::class);
        $this->handler    = new SaveAvailabilityRuleHandler($this->useCase);
        $this->resourceId = ResourceId::generate();
    }

    public function testHandleReturnsSerializedRule(): void
    {
        $rule = new AvailabilityRule($this->resourceId, DayOfWeek::Monday, '09:00', '17:00');

        $this->useCase
            ->method('execute')
            ->willReturn(new SaveAvailabilityRuleResponse($rule));

        $result = $this->handler->handle([
            'resource_id' => $this->resourceId->toString(),
            'day_of_week' => 'monday',
            'open_time'   => '09:00',
            'close_time'  => '17:00',
        ]);

        $this->assertSame($this->resourceId->toString(), $result['resource_id']);
        $this->assertSame('monday', $result['day_of_week']);
        $this->assertSame('09:00', $result['open_time']);
        $this->assertSame('17:00', $result['close_time']);
    }
}
