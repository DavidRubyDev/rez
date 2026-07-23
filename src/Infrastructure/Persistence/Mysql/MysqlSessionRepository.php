<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use DateTimeImmutable;
use PDO;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\SessionRepositoryInterface;
use Rez\Domain\Exception\SessionNotFoundException;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Session\Session;
use Rez\Domain\Session\SessionCollection;
use Rez\Domain\Session\SessionId;
use Rez\Domain\Shared\Utc;
use Rez\Infrastructure\Mapper\SessionStatusMapper;

final class MysqlSessionRepository extends MysqlRepository implements SessionRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly SessionStatusMapper $statusMapper,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws SessionNotFoundException
     * @throws DatabaseException
     */
    public function findById(SessionId $id): Session
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM sessions WHERE id = :id');
            $stmt->execute([':id' => $id->toString()]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new SessionNotFoundException();
        }

        return $this->hydrate($row);
    }

    public function findForResource(ResourceId $resourceId, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): SessionCollection
    {
        $where  = ['resource_id = :resource_id'];
        $params = [':resource_id' => $resourceId->toString()];

        if ($from !== null) {
            $where[]         = 'start_time >= :from';
            $params[':from'] = $from->format('Y-m-d H:i:s');
        }

        if ($to !== null) {
            $where[]       = 'start_time <= :to';
            $params[':to'] = $to->format('Y-m-d H:i:s');
        }

        $sql = 'SELECT * FROM sessions WHERE ' . implode(' AND ', $where);

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return SessionCollection::fromArray(array_values(array_map($this->hydrate(...), $rows)));
    }

    public function save(Session $session): void
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO sessions (id, resource_id, start_time, duration_minutes, capacity, status)
                VALUES (:id, :resource_id, :start_time, :duration_minutes, :capacity, :status)
                ON DUPLICATE KEY UPDATE
                    resource_id       = VALUES(resource_id),
                    start_time        = VALUES(start_time),
                    duration_minutes  = VALUES(duration_minutes),
                    capacity          = VALUES(capacity),
                    status            = VALUES(status)
            ');

            $stmt->execute([
                ':id'               => $session->id->toString(),
                ':resource_id'      => $session->resourceId->toString(),
                ':start_time'       => $session->startTime->format('Y-m-d H:i:s'),
                ':duration_minutes' => $session->durationMinutes,
                ':capacity'         => $session->capacity,
                ':status'           => $this->statusMapper->toString($session->status),
            ]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Session
    {
        return Session::reconstruct(
            SessionId::fromString($this->str($row['id'])),
            ResourceId::fromString($this->str($row['resource_id'])),
            new DateTimeImmutable($this->str($row['start_time']), Utc::timezone()),
            $this->int($row['duration_minutes']),
            $this->int($row['capacity']),
            $this->statusMapper->fromString($this->str($row['status'])),
        );
    }
}
