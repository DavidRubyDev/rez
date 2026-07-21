<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use DateTimeImmutable;
use PDO;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationCollection;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Domain\Shared\Utc;
use Rez\Infrastructure\Mapper\ReservationStatusMapper;

final class MysqlReservationRepository extends MysqlRepository implements ReservationRepositoryInterface
{
    private const SORT_COLUMNS = [
        'start'      => 'r.start_at',
        'end'        => 'r.end_at',
        'status'     => 'r.status',
        'party_name' => 'r.party_name',
        'created_at' => 'r.created_at',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly ReservationStatusMapper $statusMapper,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ReservationNotFoundException
     * @throws DatabaseException
     */
    public function findById(ReservationId $id): Reservation
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE id = :id');
            $stmt->execute([':id' => $id->toString()]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new ReservationNotFoundException();
        }

        return $this->hydrate($row);
    }

    public function findByTimeSlotAndResource(TimeSlot $slot, ResourceId $resourceId): ReservationCollection
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT r.* FROM reservations r
                INNER JOIN reservation_resources rr ON rr.reservation_id = r.id
                WHERE rr.resource_id = :resource_id
                  AND r.start_at < :end_at
                  AND r.end_at   > :start_at
                  AND r.status  != :cancelled_status
            ');

            $stmt->execute([
                ':resource_id'      => $resourceId->toString(),
                ':start_at'         => $slot->start->format('Y-m-d H:i:s'),
                ':end_at'           => $slot->end->format('Y-m-d H:i:s'),
                ':cancelled_status' => $this->statusMapper->toString(ReservationStatus::Cancelled),
            ]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ReservationCollection::fromArray(array_values(array_map($this->hydrate(...), $rows)));
    }

    public function findAll(?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): ReservationCollection
    {
        $where  = [];
        $params = [];

        if ($from !== null) {
            $where[]         = 'start_at >= :from';
            $params[':from'] = $from->format('Y-m-d H:i:s');
        }

        if ($to !== null) {
            $where[]       = 'end_at <= :to';
            $params[':to'] = $to->format('Y-m-d H:i:s');
        }

        $sql  = 'SELECT * FROM reservations';
        $sql .= $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ReservationCollection::fromArray(array_values(array_map($this->hydrate(...), $rows)));
    }

    public function findPage(
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        ?ResourceId $resourceId = null,
        ?ReservationStatus $status = null,
        ?string $search = null,
        ?int $offset = null,
        ?int $limit = null,
        ?string $sortBy = null,
        ?string $sortDir = null,
    ): ReservationCollection {
        [$whereSql, $params] = $this->buildPageCriteria($from, $to, $resourceId, $status, $search);

        $join = $resourceId !== null
            ? ' INNER JOIN reservation_resources rr ON rr.reservation_id = r.id'
            : '';

        $sql = 'SELECT DISTINCT r.* FROM reservations r' . $join . $whereSql;

        if ($sortBy !== null) {
            $column = self::SORT_COLUMNS[$sortBy]
                ?? throw new \InvalidArgumentException(sprintf('Unknown sort column: "%s".', $sortBy));
            $sql .= ' ORDER BY ' . $column . ' ' . ($sortDir === 'desc' ? 'DESC' : 'ASC');
        }

        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            if ($limit !== null) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
            }
            $stmt->execute();
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ReservationCollection::fromArray(array_values(array_map($this->hydrate(...), $rows)));
    }

    public function countPage(
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        ?ResourceId $resourceId = null,
        ?ReservationStatus $status = null,
        ?string $search = null,
    ): int {
        [$whereSql, $params] = $this->buildPageCriteria($from, $to, $resourceId, $status, $search);

        $join = $resourceId !== null
            ? ' INNER JOIN reservation_resources rr ON rr.reservation_id = r.id'
            : '';

        $sql = 'SELECT COUNT(DISTINCT r.id) FROM reservations r' . $join . $whereSql;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function buildPageCriteria(
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        ?ResourceId $resourceId,
        ?ReservationStatus $status,
        ?string $search,
    ): array {
        $where  = [];
        $params = [];

        if ($from !== null) {
            $where[]         = 'r.start_at >= :from';
            $params[':from'] = $from->format('Y-m-d H:i:s');
        }
        if ($to !== null) {
            $where[]       = 'r.end_at <= :to';
            $params[':to'] = $to->format('Y-m-d H:i:s');
        }
        if ($status !== null) {
            $where[]           = 'r.status = :status';
            $params[':status'] = $this->statusMapper->toString($status);
        }
        if ($search !== null) {
            $where[]           = '(r.party_name LIKE :search OR r.party_email LIKE :search OR r.party_phone LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if ($resourceId !== null) {
            $where[]                = 'rr.resource_id = :resource_id';
            $params[':resource_id'] = $resourceId->toString();
        }

        return [$where !== [] ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public function save(Reservation $reservation): void
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO reservations (id, status, start_at, end_at, party_name, party_email, party_size, party_phone, external_ref, created_at)
                VALUES (:id, :status, :start_at, :end_at, :party_name, :party_email, :party_size, :party_phone, :external_ref, :created_at)
                ON DUPLICATE KEY UPDATE
                    status       = VALUES(status),
                    start_at     = VALUES(start_at),
                    end_at       = VALUES(end_at),
                    party_name   = VALUES(party_name),
                    party_email  = VALUES(party_email),
                    party_size   = VALUES(party_size),
                    party_phone  = VALUES(party_phone),
                    external_ref = VALUES(external_ref)
            ');

            $stmt->execute([
                ':id'           => $reservation->id->toString(),
                ':status'       => $this->statusMapper->toString($reservation->status),
                ':start_at'     => $reservation->slot->start->format('Y-m-d H:i:s'),
                ':end_at'       => $reservation->slot->end->format('Y-m-d H:i:s'),
                ':party_name'   => $reservation->party->name,
                ':party_email'  => $reservation->party->email,
                ':party_size'   => $reservation->party->size,
                ':party_phone'  => $reservation->party->phone,
                ':external_ref' => $reservation->party->externalRef,
                ':created_at'   => $reservation->createdAt->format('Y-m-d H:i:s'),
            ]);

            $delete = $this->pdo->prepare('DELETE FROM reservation_resources WHERE reservation_id = :id');
            $delete->execute([':id' => $reservation->id->toString()]);

            $insert = $this->pdo->prepare('
                INSERT INTO reservation_resources (reservation_id, resource_id) VALUES (:reservation_id, :resource_id)
            ');

            foreach ($reservation->resourceIds->toArray() as $resourceId) {
                $insert->execute([
                    ':reservation_id' => $reservation->id->toString(),
                    ':resource_id'    => $resourceId->toString(),
                ]);
            }
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Reservation
    {
        $utc = Utc::timezone();

        return Reservation::reconstruct(
            ReservationId::fromString($this->str($row['id'])),
            $this->loadResourceIds($this->str($row['id'])),
            new TimeSlot(
                new DateTimeImmutable($this->str($row['start_at']), $utc),
                new DateTimeImmutable($this->str($row['end_at']), $utc),
            ),
            new Party(
                $this->str($row['party_name']),
                $this->str($row['party_email']),
                $this->int($row['party_size']),
                $this->nullStr($row['party_phone']),
                $this->nullStr($row['external_ref']),
            ),
            $this->statusMapper->fromString($this->str($row['status'])),
            new DateTimeImmutable($this->str($row['created_at']), $utc),
        );
    }

    private function loadResourceIds(string $reservationId): ResourceIdCollection
    {
        $stmt = $this->pdo->prepare('SELECT resource_id FROM reservation_resources WHERE reservation_id = :id');
        $stmt->execute([':id' => $reservationId]);

        /** @var string[] $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return ResourceIdCollection::fromArray(
            array_map(fn (string $id) => ResourceId::fromString($id), $rows)
        );
    }
}
