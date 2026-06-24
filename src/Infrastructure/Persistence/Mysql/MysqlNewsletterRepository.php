<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use PDO;
use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Domain\Exception\NewsletterSubscriberNotFoundException;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Infrastructure\Mapper\SubscriberSourceMapper;

final class MysqlNewsletterRepository extends MysqlRepository implements NewsletterRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly SubscriberSourceMapper $sourceMapper,
    ) {
    }

    /**
     * @throws NewsletterSubscriberNotFoundException
     */
    public function findByEmail(string $email): NewsletterSubscriber
    {
        $stmt = $this->pdo->prepare('SELECT * FROM newsletter_subscribers WHERE email = :email');
        $stmt->execute([':email' => $email]);

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
        $stmt = $this->pdo->prepare('SELECT * FROM newsletter_subscribers ORDER BY opted_in_at ASC');
        $stmt->execute();

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map($this->hydrate(...), $rows);
    }

    public function save(NewsletterSubscriber $subscriber): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO newsletter_subscribers (id, email, name, source, opted_in_at)
            VALUES (:id, :email, :name, :source, :opted_in_at)
            ON DUPLICATE KEY UPDATE
                name   = VALUES(name),
                source = VALUES(source)
        ');

        $stmt->execute([
            ':id'         => $subscriber->id->toString(),
            ':email'      => $subscriber->email,
            ':name'       => $subscriber->name,
            ':source'     => $this->sourceMapper->toString($subscriber->source),
            ':opted_in_at' => $subscriber->optedInAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(NewsletterSubscriberId $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM newsletter_subscribers WHERE id = :id');
        $stmt->execute([':id' => $id->toString()]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): NewsletterSubscriber
    {
        return NewsletterSubscriber::reconstruct(
            NewsletterSubscriberId::fromString($this->str($row['id'])),
            $this->str($row['email']),
            $this->nullStr($row['name']),
            $this->sourceMapper->fromString($this->str($row['source'])),
            new \DateTimeImmutable($this->str($row['opted_in_at']), new \DateTimeZone('UTC')),
        );
    }


}
