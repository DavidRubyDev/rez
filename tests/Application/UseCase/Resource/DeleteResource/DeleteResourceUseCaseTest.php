<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Resource\DeleteResource;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\UseCase\Resource\DeleteResource\DeleteResourceRequest;
use Rez\Application\UseCase\Resource\DeleteResource\DeleteResourceUseCase;
use Rez\Domain\Exception\ResourceNotFoundException;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;

class DeleteResourceUseCaseTest extends TestCase
{
    private ResourceRepositoryInterface&MockObject $repository;
    private DeleteResourceUseCase $useCase;
    private ResourceId $resourceId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ResourceRepositoryInterface::class);
        $this->useCase    = new DeleteResourceUseCase($this->repository);
        $this->resourceId = ResourceId::generate();
    }

    public function testThrowsWhenResourceNotFound(): void
    {
        $this->repository->method('findById')->willThrowException(new ResourceNotFoundException());

        $this->expectException(ResourceNotFoundException::class);

        $this->useCase->execute(new DeleteResourceRequest($this->resourceId));
    }

    public function testCallsDeleteWithCorrectId(): void
    {
        $resource = new Resource($this->resourceId, ResourceType::fromString('table'), 'Table 1', 4);
        $this->repository->method('findById')->willReturn($resource);

        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with($this->callback(fn (ResourceId $id) => $id->equals($this->resourceId)));

        $this->useCase->execute(new DeleteResourceRequest($this->resourceId));
    }

    public function testFindByIdCalledBeforeDelete(): void
    {
        $resource = new Resource($this->resourceId, ResourceType::fromString('table'), 'Table 1', 4);
        $calls    = [];

        $this->repository->method('findById')->willReturnCallback(function () use ($resource, &$calls) {
            $calls[] = 'findById';
            return $resource;
        });

        $this->repository->method('delete')->willReturnCallback(function () use (&$calls) {
            $calls[] = 'delete';
        });

        $this->useCase->execute(new DeleteResourceRequest($this->resourceId));

        $this->assertSame(['findById', 'delete'], $calls);
    }
}
