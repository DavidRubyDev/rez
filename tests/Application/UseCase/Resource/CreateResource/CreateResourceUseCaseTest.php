<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Resource\CreateResource;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
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
}
