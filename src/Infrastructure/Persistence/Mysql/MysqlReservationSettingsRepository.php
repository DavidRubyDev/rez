<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use PDO;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationSettingsRepositoryInterface;
use Rez\Domain\Reservation\ReservationSettings;

final class MysqlReservationSettingsRepository extends MysqlRepository implements ReservationSettingsRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @throws DatabaseException */
    public function get(): ReservationSettings
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM reservation_settings WHERE id = 1');
            $stmt->execute();
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new DatabaseException('Reservation settings row is missing (expected exactly one row with id = 1).');
        }

        return $this->hydrate($row);
    }

    /** @throws DatabaseException */
    public function update(ReservationSettings $settings): void
    {
        try {
            $stmt = $this->pdo->prepare('
                UPDATE reservation_settings
                SET auto_confirm                    = :auto_confirm,
                    auto_send_reservation_created    = :auto_send_reservation_created,
                    auto_send_reservation_confirmed  = :auto_send_reservation_confirmed,
                    auto_send_reservation_cancelled  = :auto_send_reservation_cancelled,
                    updated_at                       = UTC_TIMESTAMP()
                WHERE id = 1
            ');

            $stmt->execute([
                ':auto_confirm'                   => (int) $settings->autoConfirm,
                ':auto_send_reservation_created'   => (int) $settings->autoSendReservationCreated,
                ':auto_send_reservation_confirmed' => (int) $settings->autoSendReservationConfirmed,
                ':auto_send_reservation_cancelled' => (int) $settings->autoSendReservationCancelled,
            ]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ReservationSettings
    {
        return new ReservationSettings(
            $this->bool($row['auto_confirm']),
            $this->bool($row['auto_send_reservation_created']),
            $this->bool($row['auto_send_reservation_confirmed']),
            $this->bool($row['auto_send_reservation_cancelled']),
        );
    }
}
