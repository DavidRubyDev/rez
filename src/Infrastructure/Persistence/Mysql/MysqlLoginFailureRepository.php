<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use DateTimeImmutable;
use PDO;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\LoginFailureRepositoryInterface;
use Rez\Domain\Auth\LoginThrottleKey;
use Rez\Domain\Shared\Utc;

final class MysqlLoginFailureRepository extends MysqlRepository implements LoginFailureRepositoryInterface
{
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly PDO $pdo,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws DatabaseException
     */
    public function record(LoginThrottleKey $key, DateTimeImmutable $at): void
    {
        $this->execute(
            'INSERT INTO login_failures (throttle_key, failed_at) VALUES (:throttle_key, :failed_at)',
            [':throttle_key' => $key->hash, ':failed_at' => $at->format(self::DATETIME_FORMAT)],
            __METHOD__,
        );
    }

    /**
     * @return list<DateTimeImmutable>
     * @throws DatabaseException
     */
    public function failedSince(LoginThrottleKey $key, DateTimeImmutable $since): array
    {
        $stmt = $this->execute(
            'SELECT failed_at FROM login_failures WHERE throttle_key = :throttle_key AND failed_at >= :since ORDER BY failed_at',
            [':throttle_key' => $key->hash, ':since' => $since->format(self::DATETIME_FORMAT)],
            __METHOD__,
        );

        /** @var list<mixed> $values */
        $values = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_map(fn (mixed $value) => new DateTimeImmutable($this->str($value), Utc::timezone()), $values);
    }

    /**
     * @throws DatabaseException
     */
    public function clear(LoginThrottleKey $key): void
    {
        $this->execute(
            'DELETE FROM login_failures WHERE throttle_key = :throttle_key',
            [':throttle_key' => $key->hash],
            __METHOD__,
        );
    }

    /**
     * @param array<string, string> $params
     * @throws DatabaseException
     */
    private function execute(string $sql, array $params, string $operation): \PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => $operation, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return $stmt;
    }
}
