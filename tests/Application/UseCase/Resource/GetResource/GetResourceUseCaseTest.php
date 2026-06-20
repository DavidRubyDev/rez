<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Resource\GetResource;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\UseCase\Resource\GetResource\GetResourceRequest;
use Rez\Application\UseCase\Resource\GetResource\GetResourceUseCase;
use Rez\Domain\Exception\ResourceNotFoundException;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;

class GetResourceUseCaseTest extends TestCase
{
    private ResourceRepositoryInterface&MockObject $repository;
    private GetResourceUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ResourceRepositoryInterface::class);
        $this->useCase    = new GetResourceUseCase($this->repository);
    }

    public function testReturnsResourceWhenFound(): void
    {
        $id       = ResourceId::generate();
        $resource = new Resource($id, ResourceType::fromString('table'), 'Table 1', 4);

        $this->repository->method('findById')->willReturn($resource);

        $response = $this->useCase->execute(new GetResourceRequest($id));

        $this->assertSame($resource, $response->resource);
    }

    public function testThrowsWhenNotFound(): void
    {
        $this->repository->method('findById')->willThrowException(new ResourceNotFoundException());

        $this->expectException(ResourceNotFoundException::class);

        $this->useCase->execute(new GetResourceRequest(ResourceId::generate()));
    }
}
