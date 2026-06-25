<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Availability\DeleteAvailabilityRule;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\UseCase\Availability\DeleteAvailabilityRule\DeleteAvailabilityRuleRequest;
use Rez\Application\UseCase\Availability\DeleteAvailabilityRule\DeleteAvailabilityRuleUseCase;
use Rez\Domain\Availability\DayOfWeek;
use Rez\Domain\Resource\ResourceId;

class DeleteAvailabilityRuleUseCaseTest extends TestCase
{
    private AvailabilityRepositoryInterface&MockObject $availabilityRepository;
    private DeleteAvailabilityRuleUseCase $useCase;
    private ResourceId $resourceId;

    protected function setUp(): void
    {
        $this->availabilityRepository = $this->createMock(AvailabilityRepositoryInterface::class);
        $this->useCase                = new DeleteAvailabilityRuleUseCase($this->availabilityRepository);
        $this->resourceId             = ResourceId::generate();
    }

    public function testDeleteRuleCalledOnce(): void
    {
        $this->availabilityRepository->expects($this->once())->method('deleteRule');

        $this->useCase->execute(new DeleteAvailabilityRuleRequest(
            $this->resourceId,
            DayOfWeek::Monday,
        ));
    }

    public function testDeleteRuleDatabaseExceptionPropagates(): void
    {
        $this->availabilityRepository
            ->method('deleteRule')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to delete availability rule.');

        $this->useCase->execute(new DeleteAvailabilityRuleRequest(
            $this->resourceId,
            DayOfWeek::Monday,
        ));
    }
}
