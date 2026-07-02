<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use PDO;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerSettingsRepositoryInterface;
use Rez\Domain\Mailer\MailerSettings;

final class MysqlMailerSettingsRepository extends MysqlRepository implements MailerSettingsRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @throws DatabaseException */
    public function get(): MailerSettings
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM mailer_settings WHERE id = 1');
            $stmt->execute();
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new DatabaseException('Mailer settings row is missing (expected exactly one row with id = 1).');
        }

        return $this->hydrate($row);
    }

    /** @throws DatabaseException */
    public function update(MailerSettings $settings): void
    {
        try {
            $stmt = $this->pdo->prepare('
                UPDATE mailer_settings
                SET from_address = :from_address,
                    from_name    = :from_name,
                    updated_at   = UTC_TIMESTAMP()
                WHERE id = 1
            ');

            $stmt->execute([
                ':from_address' => $settings->fromAddress,
                ':from_name'    => $settings->fromName,
            ]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): MailerSettings
    {
        return new MailerSettings(
            $this->str($row['from_address']),
            $this->str($row['from_name']),
        );
    }
}
