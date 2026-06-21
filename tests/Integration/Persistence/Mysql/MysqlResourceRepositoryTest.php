<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use Rez\Domain\Exception\ResourceNotFoundException;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;
use Rez\Infrastructure\Mapper\ResourceTypeMapper;
use Rez\Infrastructure\Persistence\Mysql\MysqlResourceRepository;

class MysqlResourceRepositoryTest extends MysqlIntegrationTestCase
{
    private MysqlResourceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MysqlResourceRepository($this->pdo(), new ResourceTypeMapper());
    }

    private function makeResource(): Resource
    {
        return new Resource(
            ResourceId::generate(),
            ResourceType::fromString('table'),
            'Table 1',
            4,
            ['location' => 'main-floor'],
        );
    }

    public function testSaveAndFindByIdRoundtrip(): void
    {
        $resource = $this->makeResource();
        $this->repository->save($resource);

        $found = $this->repository->findById($resource->id);

        $this->assertTrue($resource->id->equals($found->id));
        $this->assertSame($resource->name, $found->name);
        $this->assertSame($resource->capacity, $found->capacity);
        $this->assertSame('table', $found->type->toString());
        $this->assertSame(['location' => 'main-floor'], $found->attributes);
    }

    public function testFindByIdMissingThrowsResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->repository->findById(ResourceId::generate());
    }

    public function testFindAllReturnsAllSavedResources(): void
    {
        $this->repository->save($this->makeResource());
        $this->repository->save($this->makeResource());

        $result = $this->repository->findAll();

        $this->assertSame(2, $result->count());
    }

    public function testDeleteRemovesResource(): void
    {
        $resource = $this->makeResource();
        $this->repository->save($resource);
        $this->repository->delete($resource->id);

        $this->expectException(ResourceNotFoundException::class);
        $this->repository->findById($resource->id);
    }
}
