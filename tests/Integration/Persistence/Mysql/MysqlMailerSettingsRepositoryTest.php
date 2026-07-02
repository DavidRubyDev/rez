<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use Psr\Log\NullLogger;
use Rez\Application\Exception\DatabaseException;
use Rez\Domain\Mailer\MailerSettings;
use Rez\Infrastructure\Persistence\Mysql\MysqlMailerSettingsRepository;

class MysqlMailerSettingsRepositoryTest extends MysqlIntegrationTestCase
{
    private MysqlMailerSettingsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MysqlMailerSettingsRepository($this->pdo(), new NullLogger());
    }

    public function testGetReturnsSeededDefaults(): void
    {
        $settings = $this->repository->get();

        $this->assertSame('noreply@example.com', $settings->fromAddress);
        $this->assertSame('Rez', $settings->fromName);
    }

    public function testUpdatePersistsAndGetReflectsChange(): void
    {
        $this->repository->update(new MailerSettings('info@studio.cz', 'Studio'));

        $settings = $this->repository->get();

        $this->assertSame('info@studio.cz', $settings->fromAddress);
        $this->assertSame('Studio', $settings->fromName);
    }

    public function testGetThrowsDatabaseExceptionWhenRowMissing(): void
    {
        $this->pdo()->exec('DELETE FROM mailer_settings WHERE id = 1');

        $this->expectException(DatabaseException::class);
        $this->repository->get();
    }
}
