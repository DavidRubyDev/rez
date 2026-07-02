<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\ReservationSettings\UpdateReservationSettings;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationSettingsRepositoryInterface;
use Rez\Application\UseCase\ReservationSettings\UpdateReservationSettings\UpdateReservationSettingsRequest;
use Rez\Application\UseCase\ReservationSettings\UpdateReservationSettings\UpdateReservationSettingsUseCase;
use Rez\Domain\Reservation\ReservationSettings;

class UpdateReservationSettingsUseCaseTest extends TestCase
{
    private ReservationSettingsRepositoryInterface&MockObject $repository;
    private UpdateReservationSettingsUseCase $useCase;
    private ReservationSettings $existing;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReservationSettingsRepositoryInterface::class);
        $this->useCase    = new UpdateReservationSettingsUseCase($this->repository);
        $this->existing   = new ReservationSettings(
            autoConfirm: false,
            autoSendReservationCreated: true,
            autoSendReservationConfirmed: true,
            autoSendReservationCancelled: true,
        );
    }

    public function testRepositoryGetDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('get')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load reservation settings.');

        $this->useCase->execute(new UpdateReservationSettingsRequest(autoConfirm: true));
    }

    public function testRepositoryUpdateDatabaseExceptionPropagates(): void
    {
        $this->repository->method('get')->willReturn($this->existing);
        $this->repository
            ->method('update')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to save reservation settings.');

        $this->useCase->execute(new UpdateReservationSettingsRequest(autoConfirm: true));
    }

    public function testUpdatesOnlyAutoConfirm(): void
    {
        $this->repository->method('get')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateReservationSettingsRequest(autoConfirm: true));

        $this->assertTrue($response->settings->autoConfirm);
        $this->assertTrue($response->settings->autoSendReservationCreated);
        $this->assertTrue($response->settings->autoSendReservationConfirmed);
        $this->assertTrue($response->settings->autoSendReservationCancelled);
    }

    public function testUpdatesOnlyAutoSendReservationCreated(): void
    {
        $this->repository->method('get')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateReservationSettingsRequest(autoSendReservationCreated: false));

        $this->assertFalse($response->settings->autoConfirm);
        $this->assertFalse($response->settings->autoSendReservationCreated);
        $this->assertTrue($response->settings->autoSendReservationConfirmed);
        $this->assertTrue($response->settings->autoSendReservationCancelled);
    }

    public function testUpdatesOnlyAutoSendReservationConfirmed(): void
    {
        $this->repository->method('get')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateReservationSettingsRequest(autoSendReservationConfirmed: false));

        $this->assertFalse($response->settings->autoConfirm);
        $this->assertTrue($response->settings->autoSendReservationCreated);
        $this->assertFalse($response->settings->autoSendReservationConfirmed);
        $this->assertTrue($response->settings->autoSendReservationCancelled);
    }

    public function testUpdatesOnlyAutoSendReservationCancelled(): void
    {
        $this->repository->method('get')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateReservationSettingsRequest(autoSendReservationCancelled: false));

        $this->assertFalse($response->settings->autoConfirm);
        $this->assertTrue($response->settings->autoSendReservationCreated);
        $this->assertTrue($response->settings->autoSendReservationConfirmed);
        $this->assertFalse($response->settings->autoSendReservationCancelled);
    }

    public function testUpdatesAllFieldsTogether(): void
    {
        $this->repository->method('get')->willReturn($this->existing);
        $this->repository->expects($this->once())->method('update');

        $response = $this->useCase->execute(new UpdateReservationSettingsRequest(
            autoConfirm: true,
            autoSendReservationCreated: false,
            autoSendReservationConfirmed: false,
            autoSendReservationCancelled: false,
        ));

        $this->assertTrue($response->settings->autoConfirm);
        $this->assertFalse($response->settings->autoSendReservationCreated);
        $this->assertFalse($response->settings->autoSendReservationConfirmed);
        $this->assertFalse($response->settings->autoSendReservationCancelled);
    }

    public function testNoFieldsProvidedKeepsExistingValues(): void
    {
        $this->repository->method('get')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateReservationSettingsRequest());

        $this->assertFalse($response->settings->autoConfirm);
        $this->assertTrue($response->settings->autoSendReservationCreated);
        $this->assertTrue($response->settings->autoSendReservationConfirmed);
        $this->assertTrue($response->settings->autoSendReservationCancelled);
    }
}
