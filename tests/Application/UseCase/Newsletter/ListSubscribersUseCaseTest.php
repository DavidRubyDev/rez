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

    public function testReturnsEmptyArrayWhenNoSubscribers(): void
    {
        $this->repository->method('findAll')->willReturn([]);

        $response = $this->useCase->execute(new ListSubscribersRequest());

        $this->assertSame([], $response->subscribers);
    }

    public function testReturnsAllSubscribers(): void
    {
        $subscribers = [
            $this->makeSubscriber('a@example.com'),
            $this->makeSubscriber('b@example.com'),
        ];

        $this->repository->method('findAll')->willReturn($subscribers);

        $response = $this->useCase->execute(new ListSubscribersRequest());

        $this->assertSame($subscribers, $response->subscribers);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('findAll')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load newsletter subscribers.');

        $this->useCase->execute(new ListSubscribersRequest());
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
