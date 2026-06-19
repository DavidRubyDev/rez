<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use DateTimeImmutable;
use Rez\Domain\Availability\AvailabilityOverride;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Availability\DayOfWeek;
use Rez\Domain\Resource\ResourceId;
use Rez\Infrastructure\Mapper\DayOfWeekMapper;
use Rez\Infrastructure\Persistence\Mysql\MysqlAvailabilityRepository;

class MysqlAvailabilityRepositoryTest extends MysqlIntegrationTestCase
{
    private MysqlAvailabilityRepository $repository;
    private ResourceId $resourceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MysqlAvailabilityRepository($this->pdo(), new DayOfWeekMapper());
        $this->resourceId = ResourceId::generate();
    }

    public function testSaveAndFindRulesForResourceRoundtrip(): void
    {
        $rule = new AvailabilityRule($this->resourceId, DayOfWeek::Monday, '09:00', '17:00');
        $this->repository->saveRule($rule);

        $rules = $this->repository->findRulesForResource($this->resourceId);

        $this->assertCount(1, $rules);
        $this->assertSame(DayOfWeek::Monday, $rules[0]->dayOfWeek);
        $this->assertSame('09:00', $rules[0]->openTime);
        $this->assertSame('17:00', $rules[0]->closeTime);
    }

    public function testFindRulesForResourceReturnsEmptyForUnknownResource(): void
    {
        $rules = $this->repository->findRulesForResource(ResourceId::generate());

        $this->assertCount(0, $rules);
    }

    public function testSaveAndFindOverridesForResourceRoundtrip(): void
    {
        $override = new AvailabilityOverride($this->resourceId, new DateTimeImmutable('2024-01-15'), false);
        $this->repository->saveOverride($override);

        $overrides = $this->repository->findOverridesForResource(
            $this->resourceId,
            new DateTimeImmutable('2024-01-14'),
            new DateTimeImmutable('2024-01-16'),
        );

        $this->assertCount(1, $overrides);
        $this->assertFalse($overrides[0]->isAvailable);
        $this->assertSame('2024-01-15', $overrides[0]->date->format('Y-m-d'));
    }

    public function testFindOverridesForResourceFiltersOutsideDateRange(): void
    {
        $override = new AvailabilityOverride($this->resourceId, new DateTimeImmutable('2024-01-20'), false);
        $this->repository->saveOverride($override);

        $overrides = $this->repository->findOverridesForResource(
            $this->resourceId,
            new DateTimeImmutable('2024-01-14'),
            new DateTimeImmutable('2024-01-16'),
        );

        $this->assertCount(0, $overrides);
    }
}
