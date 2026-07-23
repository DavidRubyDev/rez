<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Resource\CreateResource;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\UseCase\Resource\CreateResource\CreateResourceRequest;
use Rez\Application\UseCase\Resource\CreateResource\CreateResourceUseCase;
use Rez\Domain\Resource\Resource;

class CreateResourceUseCaseTest extends TestCase
{
    private ResourceRepositoryInterface&MockObject $repository;
    private CreateResourceUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ResourceRepositoryInterface::class);
        $this->useCase    = new CreateResourceUseCase($this->repository);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('save')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to save resource.');

        $this->useCase->execute(new CreateResourceRequest('table', 'Table 1', 4));
    }

    public function testSavesAndReturnsResource(): void
    {
        $this->repository->expects($this->once())->method('save');

        $response = $this->useCase->execute(new CreateResourceRequest('table', 'Table 1', 4));

        $this->assertInstanceOf(Resource::class, $response->resource);
        $this->assertSame('Table 1', $response->resource->name);
        $this->assertSame(4, $response->resource->capacity);
        $this->assertSame('table', $response->resource->type->toString());
    }

    public function testInvalidTypeSlugThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new CreateResourceRequest('INVALID TYPE', 'Table 1', 4));
    }

    public function testCapacityBelowOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new CreateResourceRequest('table', 'Table 1', 0));
    }

    public function testDefaultDurationMinutesDefaultsToNull(): void
    {
        $response = $this->useCase->execute(new CreateResourceRequest('table', 'Table 1', 4));

        $this->assertNull($response->resource->defaultDurationMinutes);
    }

    public function testDefaultDurationMinutesIsPassedToResource(): void
    {
        $response = $this->useCase->execute(new CreateResourceRequest('class', 'Pilates', 20, defaultDurationMinutes: 45));

        $this->assertSame(45, $response->resource->defaultDurationMinutes);
    }

    public function testPublishedDefaultsToTrue(): void
    {
        $response = $this->useCase->execute(new CreateResourceRequest('table', 'Table 1', 4));

        $this->assertTrue($response->resource->published);
    }

    public function testPublishedFalseIsPassedToResource(): void
    {
        $response = $this->useCase->execute(new CreateResourceRequest('table', 'Table 1', 4, published: false));

        $this->assertFalse($response->resource->published);
    }
}
