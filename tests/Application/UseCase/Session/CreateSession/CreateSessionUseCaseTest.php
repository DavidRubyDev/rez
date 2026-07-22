<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Session\CreateSession;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\Port\SessionRepositoryInterface;
use Rez\Application\UseCase\Session\CreateSession\CreateSessionRequest;
use Rez\Application\UseCase\Session\CreateSession\CreateSessionUseCase;
use Rez\Domain\Exception\ResourceNotFoundException;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;
use Rez\Domain\Session\SessionStatus;

class CreateSessionUseCaseTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private ResourceRepositoryInterface&MockObject $resourceRepository;
    private CreateSessionUseCase $useCase;
    private ResourceId $resourceId;

    protected function setUp(): void
    {
        $this->sessionRepository  = $this->createMock(SessionRepositoryInterface::class);
        $this->resourceRepository = $this->createMock(ResourceRepositoryInterface::class);
        $this->useCase            = new CreateSessionUseCase($this->sessionRepository, $this->resourceRepository);

        $this->resourceId = ResourceId::generate();
    }

    private function classResource(?int $defaultDurationMinutes = 45, int $capacity = 20): Resource
    {
        return new Resource($this->resourceId, ResourceType::fromString('class'), 'Pilates', $capacity, defaultDurationMinutes: $defaultDurationMinutes);
    }

    public function testThrowsResourceNotFoundExceptionWhenResourceMissing(): void
    {
        $this->resourceRepository->method('findById')->willThrowException(new ResourceNotFoundException());

        $this->expectException(ResourceNotFoundException::class);

        $this->useCase->execute(new CreateSessionRequest($this->resourceId, '2024-06-03 09:00'));
    }

    public function testResourceRepositoryDatabaseExceptionPropagates(): void
    {
        $this->resourceRepository->method('findById')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load resource.');

        $this->useCase->execute(new CreateSessionRequest($this->resourceId, '2024-06-03 09:00'));
    }

    public function testInvalidStartTimeFormatThrowsInvalidArgumentException(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->classResource());

        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new CreateSessionRequest($this->resourceId, 'not-a-date'));
    }

    public function testDurationDefaultsFromResourceWhenNotOverridden(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->classResource(defaultDurationMinutes: 45));

        $response = $this->useCase->execute(new CreateSessionRequest($this->resourceId, '2024-06-03 09:00'));

        $this->assertSame(45, $response->session->durationMinutes);
    }

    public function testDurationOverrideTakesPrecedenceOverResourceDefault(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->classResource(defaultDurationMinutes: 45));

        $response = $this->useCase->execute(new CreateSessionRequest($this->resourceId, '2024-06-03 09:00', durationMinutes: 30));

        $this->assertSame(30, $response->session->durationMinutes);
    }

    public function testNoDurationAnywhereThrowsInvalidArgumentException(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->classResource(defaultDurationMinutes: null));

        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new CreateSessionRequest($this->resourceId, '2024-06-03 09:00'));
    }

    public function testCapacityDefaultsFromResourceWhenNotOverridden(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->classResource(capacity: 20));

        $response = $this->useCase->execute(new CreateSessionRequest($this->resourceId, '2024-06-03 09:00'));

        $this->assertSame(20, $response->session->capacity);
    }

    public function testCapacityOverrideTakesPrecedenceOverResourceCapacity(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->classResource(capacity: 20));

        $response = $this->useCase->execute(new CreateSessionRequest($this->resourceId, '2024-06-03 09:00', capacity: 8));

        $this->assertSame(8, $response->session->capacity);
    }

    public function testSuccessSetsScheduledStatusAndSavesOnce(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->classResource());

        $this->sessionRepository->expects($this->once())->method('save');

        $response = $this->useCase->execute(new CreateSessionRequest($this->resourceId, '2024-06-03 09:00'));

        $this->assertSame(SessionStatus::Scheduled, $response->session->status);
        $this->assertTrue($this->resourceId->equals($response->session->resourceId));
    }

    public function testSaveDatabaseExceptionPropagates(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->classResource());
        $this->sessionRepository->method('save')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to save session.');

        $this->useCase->execute(new CreateSessionRequest($this->resourceId, '2024-06-03 09:00'));
    }
}
