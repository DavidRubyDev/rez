<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Availability\GetAvailabilityRules;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\UseCase\Availability\GetAvailabilityRules\GetAvailabilityRulesRequest;
use Rez\Application\UseCase\Availability\GetAvailabilityRules\GetAvailabilityRulesUseCase;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Availability\DayOfWeek;
use Rez\Domain\Resource\ResourceId;

class GetAvailabilityRulesUseCaseTest extends TestCase
{
    private AvailabilityRepositoryInterface&MockObject $availabilityRepository;
    private GetAvailabilityRulesUseCase $useCase;
    private ResourceId $resourceId;

    protected function setUp(): void
    {
        $this->availabilityRepository = $this->createMock(AvailabilityRepositoryInterface::class);
        $this->useCase                = new GetAvailabilityRulesUseCase($this->availabilityRepository);
        $this->resourceId             = ResourceId::generate();
    }

    public function testReturnsRulesForResource(): void
    {
        $rule = new AvailabilityRule($this->resourceId, DayOfWeek::Monday, '09:00', '17:00');

        $this->availabilityRepository
            ->method('findRulesForResource')
            ->willReturn([$rule]);

        $response = $this->useCase->execute(new GetAvailabilityRulesRequest($this->resourceId));

        $this->assertCount(1, $response->rules);
        $this->assertSame($rule, $response->rules[0]);
    }

    public function testReturnsEmptyArrayWhenNoRules(): void
    {
        $this->availabilityRepository
            ->method('findRulesForResource')
            ->willReturn([]);

        $response = $this->useCase->execute(new GetAvailabilityRulesRequest($this->resourceId));

        $this->assertSame([], $response->rules);
    }

    public function testRepositoryReceivesCorrectResourceId(): void
    {
        $this->availabilityRepository
            ->expects($this->once())
            ->method('findRulesForResource')
            ->with($this->resourceId)
            ->willReturn([]);

        $this->useCase->execute(new GetAvailabilityRulesRequest($this->resourceId));
    }

    public function testDatabaseExceptionPropagates(): void
    {
        $this->availabilityRepository
            ->method('findRulesForResource')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to get availability rules.');

        $this->useCase->execute(new GetAvailabilityRulesRequest($this->resourceId));
    }
}
