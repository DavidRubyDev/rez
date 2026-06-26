<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Availability\GetAvailabilityOverrides;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\UseCase\Availability\GetAvailabilityOverrides\GetAvailabilityOverridesRequest;
use Rez\Application\UseCase\Availability\GetAvailabilityOverrides\GetAvailabilityOverridesUseCase;
use Rez\Domain\Availability\AvailabilityOverride;
use Rez\Domain\Resource\ResourceId;

class GetAvailabilityOverridesUseCaseTest extends TestCase
{
    private AvailabilityRepositoryInterface&MockObject $availabilityRepository;
    private GetAvailabilityOverridesUseCase $useCase;
    private ResourceId $resourceId;
    private DateTimeImmutable $from;
    private DateTimeImmutable $to;

    protected function setUp(): void
    {
        $utc = new DateTimeZone('UTC');

        $this->availabilityRepository = $this->createMock(AvailabilityRepositoryInterface::class);
        $this->useCase                = new GetAvailabilityOverridesUseCase($this->availabilityRepository);
        $this->resourceId             = ResourceId::generate();
        $this->from                   = new DateTimeImmutable('2024-06-01', $utc);
        $this->to                     = new DateTimeImmutable('2024-06-30', $utc);
    }

    public function testReturnsOverridesForResource(): void
    {
        $override = new AvailabilityOverride($this->resourceId, $this->from, false);

        $this->availabilityRepository
            ->method('findOverridesForResource')
            ->willReturn([$override]);

        $response = $this->useCase->execute(
            new GetAvailabilityOverridesRequest($this->resourceId, $this->from, $this->to),
        );

        $this->assertCount(1, $response->overrides);
        $this->assertSame($override, $response->overrides[0]);
    }

    public function testReturnsEmptyArrayWhenNoOverrides(): void
    {
        $this->availabilityRepository
            ->method('findOverridesForResource')
            ->willReturn([]);

        $response = $this->useCase->execute(
            new GetAvailabilityOverridesRequest($this->resourceId, $this->from, $this->to),
        );

        $this->assertSame([], $response->overrides);
    }

    public function testRepositoryReceivesCorrectArguments(): void
    {
        $this->availabilityRepository
            ->expects($this->once())
            ->method('findOverridesForResource')
            ->with($this->resourceId, $this->from, $this->to)
            ->willReturn([]);

        $this->useCase->execute(
            new GetAvailabilityOverridesRequest($this->resourceId, $this->from, $this->to),
        );
    }

    public function testDatabaseExceptionPropagates(): void
    {
        $this->availabilityRepository
            ->method('findOverridesForResource')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to get availability overrides.');

        $this->useCase->execute(
            new GetAvailabilityOverridesRequest($this->resourceId, $this->from, $this->to),
        );
    }
}
