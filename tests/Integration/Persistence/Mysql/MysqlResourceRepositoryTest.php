<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use Psr\Log\NullLogger;
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

        $this->repository = new MysqlResourceRepository($this->pdo(), new ResourceTypeMapper(), new NullLogger());
    }

    private function makeResource(string $name = 'Table 1', string $type = 'table', int $capacity = 4): Resource
    {
        return new Resource(
            ResourceId::generate(),
            ResourceType::fromString($type),
            $name,
            $capacity,
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

    public function testDeleteSoftDeletesResourceInsteadOfRemovingIt(): void
    {
        $resource = $this->makeResource();
        $this->repository->save($resource);
        $this->repository->delete($resource->id);

        $found = $this->repository->findById($resource->id);

        $this->assertFalse($found->active);
    }

    public function testActiveDefaultsToTrueAndRoundtrips(): void
    {
        $resource = $this->makeResource();
        $this->repository->save($resource);

        $found = $this->repository->findById($resource->id);

        $this->assertTrue($found->active);
    }

    public function testFindAllExcludesDeactivatedResources(): void
    {
        $resource = $this->makeResource();
        $this->repository->save($resource);
        $this->repository->delete($resource->id);

        $result = $this->repository->findAll();

        $this->assertSame(0, $result->count());
    }

    public function testFindPageWithNoParamsMatchesFindAll(): void
    {
        $this->repository->save($this->makeResource('Table 1'));
        $this->repository->save($this->makeResource('Table 2'));

        $page = $this->repository->findPage();
        $all  = $this->repository->findAll();

        $this->assertSame($all->count(), $page->count());
        $this->assertSame(
            array_map(fn ($r) => $r->id->toString(), $all->toArray()),
            array_map(fn ($r) => $r->id->toString(), $page->toArray()),
        );
    }

    public function testFindPageExcludesDeactivatedResources(): void
    {
        $resource = $this->makeResource();
        $this->repository->save($resource);
        $this->repository->delete($resource->id);

        $this->assertSame(0, $this->repository->findPage()->count());
    }

    public function testFindPageSortsAndPaginates(): void
    {
        $this->repository->save($this->makeResource('Alice Room'));
        $this->repository->save($this->makeResource('Bob Room'));
        $this->repository->save($this->makeResource('Carol Room'));

        $page = $this->repository->findPage(sortBy: 'name', sortDir: 'desc', offset: 1, limit: 1);

        $this->assertSame(1, $page->count());
        $this->assertSame('Bob Room', $page->toArray()[0]->name);
    }

    public function testCountPageMatchesActiveResourceCount(): void
    {
        $active   = $this->makeResource('Active Room');
        $inactive = $this->makeResource('Inactive Room');

        $this->repository->save($active);
        $this->repository->save($inactive);
        $this->repository->delete($inactive->id);

        $this->assertSame(1, $this->repository->countPage());
    }

    public function testFindAllIsUnaffectedByFindPageChanges(): void
    {
        $this->repository->save($this->makeResource());
        $this->repository->save($this->makeResource());

        $this->assertSame(2, $this->repository->findAll()->count());
    }
}
