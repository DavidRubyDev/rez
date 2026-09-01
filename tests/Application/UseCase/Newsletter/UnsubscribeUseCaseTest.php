<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Newsletter;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Application\UseCase\Newsletter\Unsubscribe\UnsubscribeRequest;
use Rez\Application\UseCase\Newsletter\Unsubscribe\UnsubscribeUseCase;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Exception\NewsletterSubscriberNotFoundException;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Domain\Newsletter\SubscriberSource;
use Rez\Domain\Shared\UnsubscribeToken;

class UnsubscribeUseCaseTest extends TestCase
{
    private NewsletterRepositoryInterface&MockObject $repository;
    private UsersConfig $usersConfig;
    private UnsubscribeUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(NewsletterRepositoryInterface::class);
        $this->usersConfig = new UsersConfig('super-secret-jwt', 'super-secret-cancellation-key');
        $this->useCase    = new UnsubscribeUseCase($this->repository, $this->usersConfig);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('findByEmail')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load subscriber.');

        $this->useCase->execute(new UnsubscribeRequest('test@example.com'));
    }

    public function testUnknownEmailReturnsFalseWithoutThrowing(): void
    {
        $this->repository
            ->method('findByEmail')
            ->willThrowException(new NewsletterSubscriberNotFoundException('unknown@example.com'));

        $this->repository->expects($this->never())->method('delete');

        $response = $this->useCase->execute(new UnsubscribeRequest('unknown@example.com'));

        $this->assertFalse($response->removed);
    }

    public function testKnownEmailDeletesSubscriberAndReturnsTrue(): void
    {
        $subscriber = NewsletterSubscriber::create(
            NewsletterSubscriberId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'),
            'known@example.com',
            null,
            SubscriberSource::Guest,
        );

        $this->repository->method('findByEmail')->willReturn($subscriber);
        $this->repository->expects($this->once())->method('delete')->with($subscriber->id);

        $response = $this->useCase->execute(new UnsubscribeRequest('known@example.com'));

        $this->assertTrue($response->removed);
    }

    public function testValidTokenDeletesSubscriberAndReturnsTrue(): void
    {
        $subscriber = NewsletterSubscriber::create(
            NewsletterSubscriberId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'),
            'known@example.com',
            null,
            SubscriberSource::Guest,
        );

        $this->repository->method('findByEmail')->willReturn($subscriber);
        $this->repository->expects($this->once())->method('delete')->with($subscriber->id);

        $token = UnsubscribeToken::generate('known@example.com', $this->usersConfig->cancellationSecret);

        $response = $this->useCase->execute(new UnsubscribeRequest('known@example.com', $token->toString()));

        $this->assertTrue($response->removed);
    }

    public function testInvalidTokenThrowsInvalidTokenExceptionWithoutDeleting(): void
    {
        $subscriber = NewsletterSubscriber::create(
            NewsletterSubscriberId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'),
            'known@example.com',
            null,
            SubscriberSource::Guest,
        );

        $this->repository->method('findByEmail')->willReturn($subscriber);
        $this->repository->expects($this->never())->method('delete');

        $token = UnsubscribeToken::generate('known@example.com', 'wrong-secret');

        $this->expectException(InvalidTokenException::class);

        $this->useCase->execute(new UnsubscribeRequest('known@example.com', $token->toString()));
    }
}
