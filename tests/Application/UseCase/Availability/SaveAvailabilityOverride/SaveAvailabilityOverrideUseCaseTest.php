<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Availability\SaveAvailabilityOverride;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\UseCase\Availability\SaveAvailabilityOverride\SaveAvailabilityOverrideRequest;
use Rez\Application\UseCase\Availability\SaveAvailabilityOverride\SaveAvailabilityOverrideUseCase;
use Rez\Domain\Availability\AvailabilityOverride;
use Rez\Domain\Exception\ResourceNotFoundException;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;

class SaveAvailabilityOverrideUseCaseTest extends TestCase
{
    private AvailabilityRepositoryInterface&MockObject $availabilityRepository;
    private ResourceRepositoryInterface&MockObject $resourceRepository;
    private SaveAvailabilityOverrideUseCase $useCase;
    private Resource $resource;

    protected function setUp(): void
    {
        $this->availabilityRepository = $this->createMock(AvailabilityRepositoryInterface::class);
        $this->resourceRepository     = $this->createMock(ResourceRepositoryInterface::class);
        $this->useCase                = new SaveAvailabilityOverrideUseCase(
            $this->availabilityRepository,
            $this->resourceRepository,
        );

        $this->resource = new Resource(
            ResourceId::generate(),
            ResourceType::fromString('table'),
            'Table 1',
            4,
        );
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->resourceRepository
            ->method('findById')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load resource.');

        $this->useCase->execute(new SaveAvailabilityOverrideRequest(
            $this->resource->id,
            new DateTimeImmutable('2024-06-08'),
            false,
        ));
    }

    public function testResourceNotFoundThrows(): void
    {
        $this->resourceRepository
            ->method('findById')
            ->willThrowException(new ResourceNotFoundException());

        $this->expectException(ResourceNotFoundException::class);

        $this->useCase->execute(new SaveAvailabilityOverrideRequest(
            $this->resource->id,
            new DateTimeImmutable('2024-06-08'),
            false,
        ));
    }

    public function testSaveOverrideCalledOnce(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $this->availabilityRepository->expects($this->once())->method('saveOverride');

        $this->useCase->execute(new SaveAvailabilityOverrideRequest(
            $this->resource->id,
            new DateTimeImmutable('2024-06-08'),
            false,
        ));
    }

    public function testResponseContainsCorrectOverride(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $date     = new DateTimeImmutable('2024-06-08');
        $response = $this->useCase->execute(new SaveAvailabilityOverrideRequest(
            $this->resource->id,
            $date,
            false,
        ));

        $this->assertInstanceOf(AvailabilityOverride::class, $response->override);
        $this->assertFalse($response->override->isAvailable);
        $this->assertSame('2024-06-08', $response->override->date->format('Y-m-d'));
    }
}
