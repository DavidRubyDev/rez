<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\ReservationSettings\GetReservationSettings;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationSettingsRepositoryInterface;
use Rez\Application\UseCase\ReservationSettings\GetReservationSettings\GetReservationSettingsRequest;
use Rez\Application\UseCase\ReservationSettings\GetReservationSettings\GetReservationSettingsUseCase;
use Rez\Domain\Reservation\ReservationSettings;

class GetReservationSettingsUseCaseTest extends TestCase
{
    private ReservationSettingsRepositoryInterface&MockObject $repository;
    private GetReservationSettingsUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReservationSettingsRepositoryInterface::class);
        $this->useCase    = new GetReservationSettingsUseCase($this->repository);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('get')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load reservation settings.');

        $this->useCase->execute(new GetReservationSettingsRequest());
    }

    public function testReturnsSettingsFromRepositoryUnchanged(): void
    {
        $settings = new ReservationSettings(true, false, true, false);
        $this->repository->method('get')->willReturn($settings);

        $response = $this->useCase->execute(new GetReservationSettingsRequest());

        $this->assertSame($settings, $response->settings);
    }
}
