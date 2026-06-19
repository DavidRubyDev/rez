<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Availability\GetAvailability;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Service\AvailabilityServiceInterface;
use Rez\Application\UseCase\Availability\GetAvailability\GetAvailabilityRequest;
use Rez\Application\UseCase\Availability\GetAvailability\GetAvailabilityUseCase;
use Rez\Domain\Availability\AvailabilityWindow;
use Rez\Domain\Resource\ResourceId;

class GetAvailabilityUseCaseTest extends TestCase
{
    private AvailabilityServiceInterface&MockObject $availabilityService;
    private GetAvailabilityUseCase $useCase;
    private ResourceId $resourceId;

    protected function setUp(): void
    {
        $this->availabilityService = $this->createMock(AvailabilityServiceInterface::class);
        $this->useCase             = new GetAvailabilityUseCase($this->availabilityService);
        $this->resourceId          = ResourceId::generate();
    }

    public function testDelegatesWindowToAvailabilityService(): void
    {
        $date   = new DateTimeImmutable('2024-01-15');
        $window = AvailabilityWindow::empty($this->resourceId, $date);

        $this->availabilityService
            ->expects($this->once())
            ->method('getAvailableSlots')
            ->with($this->resourceId, $date, 60)
            ->willReturn($window);

        $response = $this->useCase->execute(new GetAvailabilityRequest($this->resourceId, $date, 60));

        $this->assertSame($window, $response->window);
    }
}
