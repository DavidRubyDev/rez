<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use Psr\Log\NullLogger;
use Rez\Application\Exception\DatabaseException;
use Rez\Domain\Reservation\ReservationSettings;
use Rez\Infrastructure\Persistence\Mysql\MysqlReservationSettingsRepository;

class MysqlReservationSettingsRepositoryTest extends MysqlIntegrationTestCase
{
    private MysqlReservationSettingsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MysqlReservationSettingsRepository($this->pdo(), new NullLogger());
    }

    public function testGetReturnsSeededDefaults(): void
    {
        $settings = $this->repository->get();

        $this->assertFalse($settings->autoConfirm);
        $this->assertTrue($settings->autoSendReservationCreated);
        $this->assertTrue($settings->autoSendReservationConfirmed);
        $this->assertTrue($settings->autoSendReservationCancelled);
    }

    public function testUpdatePersistsAndGetReflectsChange(): void
    {
        $this->repository->update(new ReservationSettings(
            autoConfirm: true,
            autoSendReservationCreated: false,
            autoSendReservationConfirmed: true,
            autoSendReservationCancelled: false,
        ));

        $settings = $this->repository->get();

        $this->assertTrue($settings->autoConfirm);
        $this->assertFalse($settings->autoSendReservationCreated);
        $this->assertTrue($settings->autoSendReservationConfirmed);
        $this->assertFalse($settings->autoSendReservationCancelled);
    }

    public function testGetThrowsDatabaseExceptionWhenRowMissing(): void
    {
        $this->pdo()->exec('DELETE FROM reservation_settings WHERE id = 1');

        $this->expectException(DatabaseException::class);
        $this->repository->get();
    }
}
