<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use PDO;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailTemplateRepositoryInterface;
use Rez\Domain\Exception\EmailTemplateNotFoundException;
use Rez\Domain\Mailer\EmailTemplate;
use Rez\Domain\Mailer\EmailTemplateId;
use Rez\Domain\Shared\Utc;

final class MysqlEmailTemplateRepository extends MysqlRepository implements EmailTemplateRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws EmailTemplateNotFoundException
     * @throws DatabaseException
     */
    public function findById(EmailTemplateId $id): EmailTemplate
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM email_templates WHERE id = :id');
            $stmt->execute([':id' => $id->toString()]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new EmailTemplateNotFoundException();
        }

        return $this->hydrate($row);
    }

    /** @return EmailTemplate[] */
    public function findAll(): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM email_templates ORDER BY created_at DESC');
            $stmt->execute();
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map($this->hydrate(...), $rows);
    }

    public function save(EmailTemplate $template): void
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO email_templates (id, subject, html, created_at)
                VALUES (:id, :subject, :html, :created_at)
                ON DUPLICATE KEY UPDATE
                    subject = VALUES(subject),
                    html    = VALUES(html)
            ');

            $stmt->execute([
                ':id'         => $template->id->toString(),
                ':subject'    => $template->subject,
                ':html'       => $template->html,
                ':created_at' => $template->createdAt->format('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function delete(EmailTemplateId $id): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM email_templates WHERE id = :id');
            $stmt->execute([':id' => $id->toString()]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): EmailTemplate
    {
        return EmailTemplate::reconstruct(
            EmailTemplateId::fromString($this->str($row['id'])),
            $this->str($row['subject']),
            $this->str($row['html']),
            new \DateTimeImmutable($this->str($row['created_at']), Utc::timezone()),
        );
    }
}
