<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use PDO;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Domain\Exception\NewsletterSubscriberNotFoundException;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Domain\Newsletter\SubscriberSource;
use Rez\Domain\Shared\Utc;
use Rez\Infrastructure\Mapper\SubscriberSourceMapper;

final class MysqlNewsletterRepository extends MysqlRepository implements NewsletterRepositoryInterface
{
    private const SORT_COLUMNS = [
        'email'       => 'email',
        'name'        => 'name',
        'source'      => 'source',
        'opted_in_at' => 'opted_in_at',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly SubscriberSourceMapper $sourceMapper,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws NewsletterSubscriberNotFoundException
     * @throws DatabaseException
     */
    public function findByEmail(string $email): NewsletterSubscriber
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM newsletter_subscribers WHERE email = :email');
            $stmt->execute([':email' => $email]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new NewsletterSubscriberNotFoundException($email);
        }

        return $this->hydrate($row);
    }

    /** @return NewsletterSubscriber[] */
    public function findAll(): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM newsletter_subscribers ORDER BY opted_in_at ASC');
            $stmt->execute();
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map($this->hydrate(...), $rows);
    }

    /** @return NewsletterSubscriber[] */
    public function findPage(
        ?string $search = null,
        ?SubscriberSource $source = null,
        ?int $offset = null,
        ?int $limit = null,
        ?string $sortBy = null,
        ?string $sortDir = null,
    ): array {
        [$whereSql, $params] = $this->buildPageCriteria($search, $source);

        $column = $sortBy !== null
            ? (self::SORT_COLUMNS[$sortBy] ?? throw new \InvalidArgumentException(sprintf('Unknown sort column: "%s".', $sortBy)))
            : 'opted_in_at';
        $dir = $sortDir === 'desc' ? 'DESC' : 'ASC';

        $sql = 'SELECT * FROM newsletter_subscribers' . $whereSql . ' ORDER BY ' . $column . ' ' . $dir;

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

        return array_map($this->hydrate(...), $rows);
    }

    public function countPage(?string $search = null, ?SubscriberSource $source = null): int
    {
        [$whereSql, $params] = $this->buildPageCriteria($search, $source);

        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM newsletter_subscribers' . $whereSql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function buildPageCriteria(?string $search, ?SubscriberSource $source): array
    {
        $where  = [];
        $params = [];

        if ($search !== null) {
            $where[]           = '(email LIKE :search OR name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if ($source !== null) {
            $where[]           = 'source = :source';
            $params[':source'] = $this->sourceMapper->toString($source);
        }

        return [$where !== [] ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public function save(NewsletterSubscriber $subscriber): void
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO newsletter_subscribers (id, email, name, source, opted_in_at)
                VALUES (:id, :email, :name, :source, :opted_in_at)
                ON DUPLICATE KEY UPDATE
                    name   = VALUES(name),
                    source = VALUES(source)
            ');

            $stmt->execute([
                ':id'          => $subscriber->id->toString(),
                ':email'       => $subscriber->email,
                ':name'        => $subscriber->name,
                ':source'      => $this->sourceMapper->toString($subscriber->source),
                ':opted_in_at' => $subscriber->optedInAt->format('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function delete(NewsletterSubscriberId $id): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM newsletter_subscribers WHERE id = :id');
            $stmt->execute([':id' => $id->toString()]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): NewsletterSubscriber
    {
        return NewsletterSubscriber::reconstruct(
            NewsletterSubscriberId::fromString($this->str($row['id'])),
            $this->str($row['email']),
            $this->nullStr($row['name']),
            $this->sourceMapper->fromString($this->str($row['source'])),
            new \DateTimeImmutable($this->str($row['opted_in_at']), Utc::timezone()),
        );
    }


}
