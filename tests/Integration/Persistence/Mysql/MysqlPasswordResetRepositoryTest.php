<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use Psr\Log\NullLogger;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Shared\Utc;
use Rez\Infrastructure\Persistence\Mysql\MysqlPasswordResetRepository;

class MysqlPasswordResetRepositoryTest extends MysqlIntegrationTestCase
{
    private MysqlPasswordResetRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MysqlPasswordResetRepository($this->pdo(), new NullLogger());
    }

    public function testSaveAndFindByTokenHash(): void
    {
        $expiresAt = Utc::now()->modify('+30 minutes');
        $this->repository->save('john@example.com', 'a-token-hash', $expiresAt);

        $record = $this->repository->findByTokenHash('a-token-hash');

        $this->assertSame('john@example.com', $record['email']);
        $this->assertSame($expiresAt->format('Y-m-d H:i:s'), $record['expires_at']->format('Y-m-d H:i:s'));
    }

    public function testFindByTokenHashThrowsWhenNotFound(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->repository->findByTokenHash('unknown-hash');
    }

    public function testSaveOverwritesPreviousTokenForEmail(): void
    {
        $this->repository->save('john@example.com', 'first-hash', Utc::now()->modify('+30 minutes'));
        $this->repository->save('john@example.com', 'second-hash', Utc::now()->modify('+60 minutes'));

        $this->expectException(InvalidTokenException::class);
        $this->repository->findByTokenHash('first-hash');
    }

    public function testDeleteByEmailRemovesToken(): void
    {
        $this->repository->save('john@example.com', 'a-token-hash', Utc::now()->modify('+30 minutes'));
        $this->repository->deleteByEmail('john@example.com');

        $this->expectException(InvalidTokenException::class);
        $this->repository->findByTokenHash('a-token-hash');
    }
}
