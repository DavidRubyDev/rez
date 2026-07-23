<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Resource\UpdateResource;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\UseCase\Resource\UpdateResource\UpdateResourceRequest;
use Rez\Application\UseCase\Resource\UpdateResource\UpdateResourceUseCase;
use Rez\Domain\Exception\ResourceNotFoundException;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;

class UpdateResourceUseCaseTest extends TestCase
{
    private ResourceRepositoryInterface&MockObject $repository;
    private UpdateResourceUseCase $useCase;
    private ResourceId $resourceId;
    private Resource $existing;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ResourceRepositoryInterface::class);
        $this->useCase    = new UpdateResourceUseCase($this->repository);
        $this->resourceId = ResourceId::generate();
        $this->existing   = new Resource(
            $this->resourceId,
            ResourceType::fromString('table'),
            'Table 1',
            4,
            ['location' => 'window'],
        );
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('findById')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load resource.');

        $this->useCase->execute(new UpdateResourceRequest($this->resourceId, 'New Name', null, null));
    }

    public function testUpdatesAllFields(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);
        $this->repository->expects($this->once())->method('save');

        $response = $this->useCase->execute(new UpdateResourceRequest(
            $this->resourceId,
            'Table One',
            6,
            ['location' => 'terrace'],
        ));

        $this->assertSame('Table One', $response->resource->name);
        $this->assertSame(6, $response->resource->capacity);
        $this->assertSame(['location' => 'terrace'], $response->resource->attributes);
    }

    public function testUpdatesOnlyName(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateResourceRequest($this->resourceId, 'Table One', null, null));

        $this->assertSame('Table One', $response->resource->name);
        $this->assertSame(4, $response->resource->capacity);
        $this->assertSame(['location' => 'window'], $response->resource->attributes);
    }

    public function testUpdatesOnlyCapacity(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateResourceRequest($this->resourceId, null, 8, null));

        $this->assertSame('Table 1', $response->resource->name);
        $this->assertSame(8, $response->resource->capacity);
        $this->assertSame(['location' => 'window'], $response->resource->attributes);
    }

    public function testUpdatesOnlyAttributes(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateResourceRequest($this->resourceId, null, null, ['floor' => 2]));

        $this->assertSame('Table 1', $response->resource->name);
        $this->assertSame(4, $response->resource->capacity);
        $this->assertSame(['floor' => 2], $response->resource->attributes);
    }

    public function testClearsAttributesWhenEmptyArrayProvided(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateResourceRequest($this->resourceId, null, null, []));

        $this->assertSame([], $response->resource->attributes);
    }

    public function testPreservesIdAndType(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateResourceRequest($this->resourceId, 'Renamed', null, null));

        $this->assertSame($this->resourceId->toString(), $response->resource->id->toString());
        $this->assertSame('table', $response->resource->type->toString());
    }

    public function testThrowsWhenResourceNotFound(): void
    {
        $this->repository->method('findById')->willThrowException(new ResourceNotFoundException());

        $this->expectException(ResourceNotFoundException::class);

        $this->useCase->execute(new UpdateResourceRequest($this->resourceId, 'Table One', null, null));
    }

    public function testThrowsWhenNameIsEmpty(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);

        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new UpdateResourceRequest($this->resourceId, '', null, null));
    }

    public function testThrowsWhenCapacityBelowOne(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);

        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new UpdateResourceRequest($this->resourceId, null, 0, null));
    }

    public function testPreservesActiveFlagAcrossUpdate(): void
    {
        $inactive = new Resource(
            $this->resourceId,
            ResourceType::fromString('table'),
            'Table 1',
            4,
            ['location' => 'window'],
            active: false,
        );
        $this->repository->method('findById')->willReturn($inactive);

        $response = $this->useCase->execute(new UpdateResourceRequest($this->resourceId, 'Renamed', null, null));

        $this->assertFalse($response->resource->active);
    }

    public function testPreservesDefaultDurationMinutesWhenNotProvided(): void
    {
        $classResource = new Resource(
            $this->resourceId,
            ResourceType::fromString('class'),
            'Pilates',
            20,
            defaultDurationMinutes: 45,
        );
        $this->repository->method('findById')->willReturn($classResource);

        $response = $this->useCase->execute(new UpdateResourceRequest($this->resourceId, 'Pilates Advanced', null, null));

        $this->assertSame(45, $response->resource->defaultDurationMinutes);
    }

    public function testUpdatesDefaultDurationMinutesWhenProvided(): void
    {
        $classResource = new Resource(
            $this->resourceId,
            ResourceType::fromString('class'),
            'Pilates',
            20,
            defaultDurationMinutes: 45,
        );
        $this->repository->method('findById')->willReturn($classResource);

        $response = $this->useCase->execute(new UpdateResourceRequest($this->resourceId, null, null, null, defaultDurationMinutes: 30));

        $this->assertSame(30, $response->resource->defaultDurationMinutes);
    }

    public function testPreservesPublishedFlagWhenNotProvided(): void
    {
        $unpublished = new Resource(
            $this->resourceId,
            ResourceType::fromString('table'),
            'Table 1',
            4,
            published: false,
        );
        $this->repository->method('findById')->willReturn($unpublished);

        $response = $this->useCase->execute(new UpdateResourceRequest($this->resourceId, 'Renamed', null, null));

        $this->assertFalse($response->resource->published);
    }

    public function testUpdatesPublishedFlagWhenProvided(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateResourceRequest($this->resourceId, null, null, null, published: false));

        $this->assertFalse($response->resource->published);
    }
}
