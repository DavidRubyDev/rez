<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Resource\ListResources;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\UseCase\Resource\ListResources\ListResourcesRequest;
use Rez\Application\UseCase\Resource\ListResources\ListResourcesUseCase;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceCollection;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;

class ListResourcesUseCaseTest extends TestCase
{
    private ResourceRepositoryInterface&MockObject $repository;
    private ListResourcesUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ResourceRepositoryInterface::class);
        $this->useCase    = new ListResourcesUseCase($this->repository);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('findPage')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to list resources.');

        $this->useCase->execute(new ListResourcesRequest());
    }

    public function testReturnsResourcesAndTotalFromRepository(): void
    {
        $collection = ResourceCollection::fromArray([
            new Resource(ResourceId::generate(), ResourceType::fromString('table'), 'Table 1', 4),
            new Resource(ResourceId::generate(), ResourceType::fromString('table'), 'Table 2', 2),
        ]);

        $this->repository->method('findPage')->willReturn($collection);
        $this->repository->method('countPage')->willReturn(2);

        $response = $this->useCase->execute(new ListResourcesRequest());

        $this->assertSame($collection, $response->resources);
        $this->assertSame(2, $response->total);
    }

    public function testPassesPaginationAndSortThroughToRepository(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findPage')
            ->with(10, 20, 'name', 'desc')
            ->willReturn(ResourceCollection::empty());

        $this->repository->method('countPage')->willReturn(0);

        $this->useCase->execute(new ListResourcesRequest(offset: 10, limit: 20, sortBy: 'name', sortDir: 'desc'));
    }

    public function testInvalidSortByThrowsInvalidArgumentException(): void
    {
        $this->repository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListResourcesRequest(sortBy: 'not_a_column'));
    }

    public function testInvalidLimitThrowsInvalidArgumentException(): void
    {
        $this->repository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListResourcesRequest(limit: 101));
    }

    public function testNegativeOffsetThrowsInvalidArgumentException(): void
    {
        $this->repository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListResourcesRequest(offset: -1));
    }

    public function testInvalidSortDirThrowsInvalidArgumentException(): void
    {
        $this->repository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListResourcesRequest(sortDir: 'sideways'));
    }
}
