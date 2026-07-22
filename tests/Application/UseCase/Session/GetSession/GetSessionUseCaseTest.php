<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Session\GetSession;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\SessionRepositoryInterface;
use Rez\Application\UseCase\Session\GetSession\GetSessionRequest;
use Rez\Application\UseCase\Session\GetSession\GetSessionUseCase;
use Rez\Domain\Exception\SessionNotFoundException;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Session\Session;
use Rez\Domain\Session\SessionId;

class GetSessionUseCaseTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $repository;
    private GetSessionUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SessionRepositoryInterface::class);
        $this->useCase    = new GetSessionUseCase($this->repository);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('findById')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load session.');

        $this->useCase->execute(new GetSessionRequest(SessionId::generate()));
    }

    public function testReturnsSessionWhenFound(): void
    {
        $id      = SessionId::generate();
        $session = Session::create($id, ResourceId::generate(), new DateTimeImmutable('2024-06-03 09:00:00'), 60, 10);

        $this->repository->method('findById')->willReturn($session);

        $response = $this->useCase->execute(new GetSessionRequest($id));

        $this->assertSame($session, $response->session);
    }

    public function testThrowsWhenNotFound(): void
    {
        $this->repository->method('findById')->willThrowException(new SessionNotFoundException());

        $this->expectException(SessionNotFoundException::class);

        $this->useCase->execute(new GetSessionRequest(SessionId::generate()));
    }
}
