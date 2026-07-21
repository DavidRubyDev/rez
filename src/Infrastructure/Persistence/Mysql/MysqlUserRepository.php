<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use PDO;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserCollection;
use Rez\Domain\User\UserId;
use Rez\Domain\User\UserRole;
use Rez\Infrastructure\Mapper\UserRoleMapper;

final class MysqlUserRepository extends MysqlRepository implements UserRepositoryInterface
{
    private const SORT_COLUMNS = [
        'name'       => 'name',
        'email'      => 'email',
        'role'       => 'role',
        'created_at' => 'created_at',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly UserRoleMapper $roleMapper,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws UserNotFoundException
     * @throws DatabaseException
     */
    public function findById(UserId $id): User
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
            $stmt->execute([':id' => $id->toString()]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new UserNotFoundException($id->toString());
        }

        return $this->hydrate($row);
    }

    /**
     * @throws UserNotFoundException
     * @throws DatabaseException
     */
    public function findByEmail(string $email): User
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
            $stmt->execute([':email' => $email]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new UserNotFoundException($email);
        }

        return $this->hydrate($row);
    }

    public function findAll(): UserCollection
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM users ORDER BY created_at ASC');
            $stmt->execute();
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return UserCollection::fromArray(array_map($this->hydrate(...), $rows));
    }

    public function findPage(
        ?string $search = null,
        ?UserRole $role = null,
        ?int $offset = null,
        ?int $limit = null,
        ?string $sortBy = null,
        ?string $sortDir = null,
    ): UserCollection {
        [$whereSql, $params] = $this->buildPageCriteria($search, $role);

        $column = $sortBy !== null
            ? (self::SORT_COLUMNS[$sortBy] ?? throw new \InvalidArgumentException(sprintf('Unknown sort column: "%s".', $sortBy)))
            : 'created_at';
        $dir = $sortDir === 'desc' ? 'DESC' : 'ASC';

        $sql = 'SELECT * FROM users' . $whereSql . ' ORDER BY ' . $column . ' ' . $dir;

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

        return UserCollection::fromArray(array_map($this->hydrate(...), $rows));
    }

    public function countPage(?string $search = null, ?UserRole $role = null): int
    {
        [$whereSql, $params] = $this->buildPageCriteria($search, $role);

        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users' . $whereSql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function buildPageCriteria(?string $search, ?UserRole $role): array
    {
        $where  = [];
        $params = [];

        if ($search !== null) {
            $where[]           = '(name LIKE :search OR email LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if ($role !== null) {
            $where[]         = 'role = :role';
            $params[':role'] = $this->roleMapper->toString($role);
        }

        return [$where !== [] ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public function save(User $user): void
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO users (id, name, email, password_hash, role, newsletter_opt_in, stripe_customer_id, created_at)
                VALUES (:id, :name, :email, :password_hash, :role, :newsletter_opt_in, :stripe_customer_id, :created_at)
                ON DUPLICATE KEY UPDATE
                    name               = VALUES(name),
                    email              = VALUES(email),
                    password_hash      = VALUES(password_hash),
                    role               = VALUES(role),
                    newsletter_opt_in  = VALUES(newsletter_opt_in),
                    stripe_customer_id = VALUES(stripe_customer_id)
            ');

            $stmt->execute([
                ':id'                 => $user->id->toString(),
                ':name'               => $user->name,
                ':email'              => $user->email,
                ':password_hash'      => $user->password->toString(),
                ':role'               => $this->roleMapper->toString($user->role),
                ':newsletter_opt_in'  => $user->newsletterOptIn ? 1 : 0,
                ':stripe_customer_id' => $user->stripeCustomerId,
                ':created_at'         => $user->createdAt->format('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function delete(UserId $id): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute([':id' => $id->toString()]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): User
    {
        return User::reconstruct(
            UserId::fromString($this->str($row['id'])),
            $this->str($row['name']),
            $this->str($row['email']),
            HashedPassword::fromHash($this->str($row['password_hash'])),
            $this->roleMapper->fromString($this->str($row['role'])),
            $this->bool($row['newsletter_opt_in']),
            $this->nullStr($row['stripe_customer_id']),
            new \DateTimeImmutable($this->str($row['created_at']), Utc::timezone()),
        );
    }
}
