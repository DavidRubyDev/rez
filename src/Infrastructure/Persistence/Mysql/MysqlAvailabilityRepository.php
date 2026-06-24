<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use DateTimeImmutable;
use PDO;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Domain\Availability\AvailabilityOverride;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Resource\ResourceId;
use Rez\Infrastructure\Mapper\DayOfWeekMapper;

final class MysqlAvailabilityRepository extends MysqlRepository implements AvailabilityRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly DayOfWeekMapper $dayMapper,
    ) {
    }

    public function findRulesForResource(ResourceId $resourceId): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM availability_rules WHERE resource_id = :resource_id');
            $stmt->execute([':resource_id' => $resourceId->toString()]);
        } catch (\PDOException $e) {
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map($this->hydrateRule(...), $rows);
    }

    public function findOverridesForResource(ResourceId $resourceId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT * FROM availability_overrides
                WHERE resource_id = :resource_id
                  AND date >= :from
                  AND date < :to
            ');

            $stmt->execute([
                ':resource_id' => $resourceId->toString(),
                ':from'        => $from->format('Y-m-d'),
                ':to'          => $to->format('Y-m-d'),
            ]);
        } catch (\PDOException $e) {
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map($this->hydrateOverride(...), $rows);
    }

    public function saveRule(AvailabilityRule $rule): void
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO availability_rules (resource_id, day_of_week, open_time, close_time)
                VALUES (:resource_id, :day_of_week, :open_time, :close_time)
                ON DUPLICATE KEY UPDATE
                    open_time  = VALUES(open_time),
                    close_time = VALUES(close_time)
            ');

            $stmt->execute([
                ':resource_id' => $rule->resourceId->toString(),
                ':day_of_week' => $this->dayMapper->toString($rule->dayOfWeek),
                ':open_time'   => $rule->openTime,
                ':close_time'  => $rule->closeTime,
            ]);
        } catch (\PDOException $e) {
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function saveOverride(AvailabilityOverride $override): void
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO availability_overrides (resource_id, date, available)
                VALUES (:resource_id, :date, :available)
                ON DUPLICATE KEY UPDATE
                    available = VALUES(available)
            ');

            $stmt->execute([
                ':resource_id' => $override->resourceId->toString(),
                ':date'        => $override->date->format('Y-m-d'),
                ':available'   => $override->isAvailable ? 1 : 0,
            ]);
        } catch (\PDOException $e) {
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrateRule(array $row): AvailabilityRule
    {
        return new AvailabilityRule(
            ResourceId::fromString($this->str($row['resource_id'])),
            $this->dayMapper->fromString($this->str($row['day_of_week'])),
            $this->str($row['open_time']),
            $this->str($row['close_time']),
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateOverride(array $row): AvailabilityOverride
    {
        return new AvailabilityOverride(
            ResourceId::fromString($this->str($row['resource_id'])),
            new DateTimeImmutable($this->str($row['date'])),
            $this->bool($row['available']),
        );
    }

}
