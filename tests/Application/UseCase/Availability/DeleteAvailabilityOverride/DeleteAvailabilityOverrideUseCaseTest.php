<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Availability\DeleteAvailabilityOverride;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\UseCase\Availability\DeleteAvailabilityOverride\DeleteAvailabilityOverrideRequest;
use Rez\Application\UseCase\Availability\DeleteAvailabilityOverride\DeleteAvailabilityOverrideUseCase;
use Rez\Domain\Resource\ResourceId;

class DeleteAvailabilityOverrideUseCaseTest extends TestCase
{
    private AvailabilityRepositoryInterface&MockObject $availabilityRepository;
    private DeleteAvailabilityOverrideUseCase $useCase;
    private ResourceId $resourceId;
    private DateTimeImmutable $date;

    protected function setUp(): void
    {
        $this->availabilityRepository = $this->createMock(AvailabilityRepositoryInterface::class);
        $this->useCase                = new DeleteAvailabilityOverrideUseCase($this->availabilityRepository);
        $this->resourceId             = ResourceId::generate();
        $this->date                   = new DateTimeImmutable('2024-01-15', new DateTimeZone('UTC'));
    }

    public function testDeleteOverrideCalledOnce(): void
    {
        $this->availabilityRepository->expects($this->once())->method('deleteOverride');

        $this->useCase->execute(new DeleteAvailabilityOverrideRequest(
            $this->resourceId,
            $this->date,
        ));
    }

    public function testDeleteOverrideDatabaseExceptionPropagates(): void
    {
        $this->availabilityRepository
            ->method('deleteOverride')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to delete availability override.');

        $this->useCase->execute(new DeleteAvailabilityOverrideRequest(
            $this->resourceId,
            $this->date,
        ));
    }
}
