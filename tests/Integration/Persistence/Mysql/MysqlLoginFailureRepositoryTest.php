<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use Psr\Log\NullLogger;
use Rez\Domain\Auth\LoginThrottleKey;
use Rez\Domain\Shared\Utc;
use Rez\Infrastructure\Persistence\Mysql\MysqlLoginFailureRepository;

class MysqlLoginFailureRepositoryTest extends MysqlIntegrationTestCase
{
    private MysqlLoginFailureRepository $repository;
    private LoginThrottleKey $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MysqlLoginFailureRepository($this->pdo(), new NullLogger());
        $this->key        = LoginThrottleKey::for('john@example.com', '203.0.113.7');
    }

    public function testRecordedFailuresComeBackOldestFirst(): void
    {
        $older = Utc::now()->modify('-10 minutes');
        $newer = Utc::now()->modify('-5 minutes');
        $this->repository->record($this->key, $newer);
        $this->repository->record($this->key, $older);

        $failures = $this->repository->failedSince($this->key, Utc::now()->modify('-1 hour'));

        $this->assertSame(
            [$older->format('Y-m-d H:i:s'), $newer->format('Y-m-d H:i:s')],
            array_map(fn (\DateTimeImmutable $at) => $at->format('Y-m-d H:i:s'), $failures),
        );
    }

    public function testFailuresBeforeTheWindowAreLeftOut(): void
    {
        $this->repository->record($this->key, Utc::now()->modify('-20 minutes'));
        $this->repository->record($this->key, Utc::now()->modify('-5 minutes'));

        $this->assertCount(1, $this->repository->failedSince($this->key, Utc::now()->modify('-15 minutes')));
    }

    public function testFailuresAreKeptApartPerKey(): void
    {
        $this->repository->record($this->key, Utc::now());
        $this->repository->record(LoginThrottleKey::for('john@example.com', '198.51.100.1'), Utc::now());

        $this->assertCount(1, $this->repository->failedSince($this->key, Utc::now()->modify('-1 hour')));
    }

    public function testClearRemovesOnlyThatKeysFailures(): void
    {
        $otherKey = LoginThrottleKey::for('jane@example.com', '203.0.113.7');
        $this->repository->record($this->key, Utc::now());
        $this->repository->record($otherKey, Utc::now());

        $this->repository->clear($this->key);

        $this->assertSame([], $this->repository->failedSince($this->key, Utc::now()->modify('-1 hour')));
        $this->assertCount(1, $this->repository->failedSince($otherKey, Utc::now()->modify('-1 hour')));
    }

    public function testFailedAtIsReadBackInUtc(): void
    {
        $this->repository->record($this->key, Utc::now());

        $this->assertSame('UTC', $this->repository->failedSince($this->key, Utc::now()->modify('-1 hour'))[0]->getTimezone()->getName());
    }
}
