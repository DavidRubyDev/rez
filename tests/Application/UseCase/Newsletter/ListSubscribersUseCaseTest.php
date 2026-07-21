<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Newsletter;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Application\UseCase\Newsletter\ListSubscribers\ListSubscribersRequest;
use Rez\Application\UseCase\Newsletter\ListSubscribers\ListSubscribersUseCase;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Domain\Newsletter\SubscriberSource;

class ListSubscribersUseCaseTest extends TestCase
{
    private NewsletterRepositoryInterface&MockObject $repository;
    private ListSubscribersUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(NewsletterRepositoryInterface::class);
        $this->useCase    = new ListSubscribersUseCase($this->repository);
    }

    public function testReturnsEmptyArrayAndZeroTotalWhenNoSubscribers(): void
    {
        $this->repository->method('findPage')->willReturn([]);
        $this->repository->method('countPage')->willReturn(0);

        $response = $this->useCase->execute(new ListSubscribersRequest());

        $this->assertSame([], $response->subscribers);
        $this->assertSame(0, $response->total);
    }

    public function testReturnsSubscribersAndTotal(): void
    {
        $subscribers = [
            $this->makeSubscriber('a@example.com'),
            $this->makeSubscriber('b@example.com'),
        ];

        $this->repository->method('findPage')->willReturn($subscribers);
        $this->repository->method('countPage')->willReturn(2);

        $response = $this->useCase->execute(new ListSubscribersRequest());

        $this->assertSame($subscribers, $response->subscribers);
        $this->assertSame(2, $response->total);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('findPage')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load newsletter subscribers.');

        $this->useCase->execute(new ListSubscribersRequest());
    }

    public function testPassesFiltersThroughToRepository(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findPage')
            ->with('jane', SubscriberSource::Registered, null, null, null, null)
            ->willReturn([]);

        $this->repository
            ->expects($this->once())
            ->method('countPage')
            ->with('jane', SubscriberSource::Registered)
            ->willReturn(0);

        $this->useCase->execute(new ListSubscribersRequest(search: 'jane', source: SubscriberSource::Registered));
    }

    public function testPassesPaginationAndSortThroughToRepository(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findPage')
            ->with(null, null, 10, 20, 'email', 'desc')
            ->willReturn([]);

        $this->repository->method('countPage')->willReturn(0);

        $this->useCase->execute(new ListSubscribersRequest(offset: 10, limit: 20, sortBy: 'email', sortDir: 'desc'));
    }

    public function testInvalidSortByThrowsInvalidArgumentException(): void
    {
        $this->repository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListSubscribersRequest(sortBy: 'not_a_column'));
    }

    public function testInvalidLimitThrowsInvalidArgumentException(): void
    {
        $this->repository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListSubscribersRequest(limit: 101));
    }

    public function testNegativeOffsetThrowsInvalidArgumentException(): void
    {
        $this->repository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListSubscribersRequest(offset: -1));
    }

    public function testInvalidSortDirThrowsInvalidArgumentException(): void
    {
        $this->repository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListSubscribersRequest(sortDir: 'sideways'));
    }

    private function makeSubscriber(string $email): NewsletterSubscriber
    {
        return NewsletterSubscriber::create(
            NewsletterSubscriberId::generate(),
            $email,
            null,
            SubscriberSource::Guest,
        );
    }
}
