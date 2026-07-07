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
            ->method('findAll')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to list resources.');

        $this->useCase->execute(new ListResourcesRequest());
    }

    public function testReturnsAllActiveResources(): void
    {
        $collection = ResourceCollection::fromArray([
            new Resource(ResourceId::generate(), ResourceType::fromString('table'), 'Table 1', 4),
            new Resource(ResourceId::generate(), ResourceType::fromString('table'), 'Table 2', 2),
        ]);

        $this->repository->method('findAll')->willReturn($collection);

        $response = $this->useCase->execute(new ListResourcesRequest());

        $this->assertSame(2, $response->resources->count());
    }

    public function testExcludesInactiveResources(): void
    {
        $active   = new Resource(ResourceId::generate(), ResourceType::fromString('table'), 'Table 1', 4);
        $inactive = new Resource(ResourceId::generate(), ResourceType::fromString('table'), 'Table 2', 2, active: false);

        $this->repository->method('findAll')->willReturn(ResourceCollection::fromArray([$active, $inactive]));

        $response = $this->useCase->execute(new ListResourcesRequest());

        $this->assertSame(1, $response->resources->count());
        $this->assertTrue($response->resources->findById($active->id)?->active);
        $this->assertNull($response->resources->findById($inactive->id));
    }
}
