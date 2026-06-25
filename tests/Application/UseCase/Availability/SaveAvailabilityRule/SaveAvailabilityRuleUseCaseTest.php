<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Availability\SaveAvailabilityRule;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\UseCase\Availability\SaveAvailabilityRule\SaveAvailabilityRuleRequest;
use Rez\Application\UseCase\Availability\SaveAvailabilityRule\SaveAvailabilityRuleUseCase;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Availability\DayOfWeek;
use Rez\Domain\Exception\ResourceNotFoundException;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;

class SaveAvailabilityRuleUseCaseTest extends TestCase
{
    private AvailabilityRepositoryInterface&MockObject $availabilityRepository;
    private ResourceRepositoryInterface&MockObject $resourceRepository;
    private SaveAvailabilityRuleUseCase $useCase;
    private Resource $resource;

    protected function setUp(): void
    {
        $this->availabilityRepository = $this->createMock(AvailabilityRepositoryInterface::class);
        $this->resourceRepository     = $this->createMock(ResourceRepositoryInterface::class);
        $this->useCase                = new SaveAvailabilityRuleUseCase(
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

        $this->useCase->execute(new SaveAvailabilityRuleRequest(
            $this->resource->id,
            DayOfWeek::Monday,
            '09:00',
            '17:00',
        ));
    }

    public function testResourceNotFoundThrows(): void
    {
        $this->resourceRepository
            ->method('findById')
            ->willThrowException(new ResourceNotFoundException());

        $this->expectException(ResourceNotFoundException::class);

        $this->useCase->execute(new SaveAvailabilityRuleRequest(
            $this->resource->id,
            DayOfWeek::Monday,
            '09:00',
            '17:00',
        ));
    }

    public function testInvalidTimesThrow(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new SaveAvailabilityRuleRequest(
            $this->resource->id,
            DayOfWeek::Monday,
            '17:00',
            '09:00',
        ));
    }

    public function testSaveRuleCalledOnce(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $this->availabilityRepository->expects($this->once())->method('saveRule');

        $this->useCase->execute(new SaveAvailabilityRuleRequest(
            $this->resource->id,
            DayOfWeek::Monday,
            '09:00',
            '17:00',
        ));
    }

    public function testResponseContainsCorrectRule(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $response = $this->useCase->execute(new SaveAvailabilityRuleRequest(
            $this->resource->id,
            DayOfWeek::Monday,
            '09:00',
            '17:00',
        ));

        $this->assertInstanceOf(AvailabilityRule::class, $response->rule);
        $this->assertSame(DayOfWeek::Monday, $response->rule->dayOfWeek);
        $this->assertSame('09:00', $response->rule->openTime);
        $this->assertSame('17:00', $response->rule->closeTime);
    }

    public function testInvalidValidFromFormatThrows(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new SaveAvailabilityRuleRequest(
            $this->resource->id,
            DayOfWeek::Monday,
            '09:00',
            '17:00',
            validFrom: 'not-a-date',
        ));
    }

    public function testInvalidValidUntilFormatThrows(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new SaveAvailabilityRuleRequest(
            $this->resource->id,
            DayOfWeek::Monday,
            '09:00',
            '17:00',
            validUntil: 'not-a-date',
        ));
    }

    public function testValidFromAfterValidUntilThrows(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new SaveAvailabilityRuleRequest(
            $this->resource->id,
            DayOfWeek::Monday,
            '09:00',
            '17:00',
            validFrom:  '2024-06-01',
            validUntil: '2024-01-01',
        ));
    }

    public function testBoundsArePassedToRule(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $response = $this->useCase->execute(new SaveAvailabilityRuleRequest(
            $this->resource->id,
            DayOfWeek::Monday,
            '09:00',
            '17:00',
            validFrom:  '2024-01-01',
            validUntil: '2024-03-31',
        ));

        $this->assertSame('2024-01-01', $response->rule->validFrom?->format('Y-m-d'));
        $this->assertSame('2024-03-31', $response->rule->validUntil?->format('Y-m-d'));
    }
}
