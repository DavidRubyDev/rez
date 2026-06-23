<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use DateTimeImmutable;
use PDO;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationCollection;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Infrastructure\Mapper\ReservationStatusMapper;

final class MysqlReservationRepository extends MysqlRepository implements ReservationRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ReservationStatusMapper $statusMapper,
    ) {
    }

    public function findById(ReservationId $id): Reservation
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE id = :id');
        $stmt->execute([':id' => $id->toString()]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new ReservationNotFoundException();
        }

        return $this->hydrate($row);
    }

    public function findByTimeSlotAndResource(TimeSlot $slot, ResourceId $resourceId): ReservationCollection
    {
        $stmt = $this->pdo->prepare('
            SELECT r.* FROM reservations r
            INNER JOIN reservation_resources rr ON rr.reservation_id = r.id
            WHERE rr.resource_id = :resource_id
              AND r.start_at < :end_at
              AND r.end_at   > :start_at
        ');

        $stmt->execute([
            ':resource_id' => $resourceId->toString(),
            ':start_at'    => $slot->start->format('Y-m-d H:i:s'),
            ':end_at'      => $slot->end->format('Y-m-d H:i:s'),
        ]);

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

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ReservationCollection::fromArray(array_values(array_map($this->hydrate(...), $rows)));
    }

    public function save(Reservation $reservation): void
    {
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
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Reservation
    {
        return Reservation::reconstruct(
            ReservationId::fromString($this->str($row['id'])),
            $this->loadResourceIds($this->str($row['id'])),
            new TimeSlot(
                new DateTimeImmutable($this->str($row['start_at'])),
                new DateTimeImmutable($this->str($row['end_at'])),
            ),
            new Party(
                $this->str($row['party_name']),
                $this->str($row['party_email']),
                $this->int($row['party_size']),
                $this->nullStr($row['party_phone']),
                $this->nullStr($row['external_ref']),
            ),
            $this->statusMapper->fromString($this->str($row['status'])),
            new DateTimeImmutable($this->str($row['created_at'])),
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
