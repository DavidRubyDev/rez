<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\NullLogger;
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

        $this->repository = new MysqlAvailabilityRepository($this->pdo(), new DayOfWeekMapper(), new NullLogger());
        $this->resourceId = $this->insertResource();
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

    public function testSaveRuleIsIdempotent(): void
    {
        $this->repository->saveRule(new AvailabilityRule($this->resourceId, DayOfWeek::Monday, '09:00', '17:00'));
        $this->repository->saveRule(new AvailabilityRule($this->resourceId, DayOfWeek::Monday, '10:00', '18:00'));

        $rules = $this->repository->findRulesForResource($this->resourceId);

        $this->assertCount(1, $rules);
        $this->assertSame('10:00', $rules[0]->openTime);
        $this->assertSame('18:00', $rules[0]->closeTime);
    }

    public function testFindRulesForResourceReturnsMultipleRules(): void
    {
        $this->repository->saveRule(new AvailabilityRule($this->resourceId, DayOfWeek::Monday, '09:00', '17:00'));
        $this->repository->saveRule(new AvailabilityRule($this->resourceId, DayOfWeek::Tuesday, '09:00', '17:00'));
        $this->repository->saveRule(new AvailabilityRule($this->resourceId, DayOfWeek::Saturday, '10:00', '14:00'));

        $rules = $this->repository->findRulesForResource($this->resourceId);

        $this->assertCount(3, $rules);
    }

    public function testRuleWithBoundsRoundtrips(): void
    {
        $validFrom  = new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC'));
        $validUntil = new DateTimeImmutable('2024-03-31 00:00:00', new DateTimeZone('UTC'));
        $rule       = new AvailabilityRule(
            $this->resourceId,
            DayOfWeek::Monday,
            '09:00',
            '17:00',
            validFrom: $validFrom,
            validUntil: $validUntil,
        );
        $this->repository->saveRule($rule);

        $rules = $this->repository->findRulesForResource($this->resourceId);

        $this->assertCount(1, $rules);
        $this->assertSame('2024-01-01', $rules[0]->validFrom?->format('Y-m-d'));
        $this->assertSame('2024-03-31', $rules[0]->validUntil?->format('Y-m-d'));
    }

    public function testRuleWithoutBoundsRoundtripsAsNull(): void
    {
        $this->repository->saveRule(new AvailabilityRule($this->resourceId, DayOfWeek::Monday, '09:00', '17:00'));

        $rules = $this->repository->findRulesForResource($this->resourceId);

        $this->assertCount(1, $rules);
        $this->assertNull($rules[0]->validFrom);
        $this->assertNull($rules[0]->validUntil);
    }

    public function testDeleteRuleRemovesIt(): void
    {
        $this->repository->saveRule(new AvailabilityRule($this->resourceId, DayOfWeek::Monday, '09:00', '17:00'));
        $this->repository->deleteRule($this->resourceId, DayOfWeek::Monday);

        $this->assertCount(0, $this->repository->findRulesForResource($this->resourceId));
    }

    public function testDeleteOverrideRemovesIt(): void
    {
        $date = new \DateTimeImmutable('2024-01-15', new \DateTimeZone('UTC'));
        $this->repository->saveOverride(new AvailabilityOverride($this->resourceId, $date, false));
        $this->repository->deleteOverride($this->resourceId, $date);

        $overrides = $this->repository->findOverridesForResource(
            $this->resourceId,
            new \DateTimeImmutable('2024-01-14'),
            new \DateTimeImmutable('2024-01-16'),
        );

        $this->assertCount(0, $overrides);
    }

    public function testSaveOverrideIsIdempotent(): void
    {
        $date = new DateTimeImmutable('2024-01-15');
        $this->repository->saveOverride(new AvailabilityOverride($this->resourceId, $date, false));
        $this->repository->saveOverride(new AvailabilityOverride($this->resourceId, $date, true));

        $overrides = $this->repository->findOverridesForResource(
            $this->resourceId,
            new DateTimeImmutable('2024-01-14'),
            new DateTimeImmutable('2024-01-16'),
        );

        $this->assertCount(1, $overrides);
        $this->assertTrue($overrides[0]->isAvailable);
    }
}
