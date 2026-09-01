<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Newsletter;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeRequest;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCase;
use Rez\Domain\Exception\NewsletterSubscriberNotFoundException;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Domain\Newsletter\SubscriberSource;
use Rez\Domain\Shared\UnsubscribeToken;

class SubscribeUseCaseTest extends TestCase
{
    private NewsletterRepositoryInterface&MockObject $repository;
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;
    private UsersConfig $usersConfig;
    private SubscribeUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository  = $this->createMock(NewsletterRepositoryInterface::class);
        $this->mailer      = $this->createMock(MailerInterface::class);
        $this->logger      = $this->createMock(LoggerInterface::class);
        $this->usersConfig = new UsersConfig(jwtSecret: 'jwt-secret', cancellationSecret: 'secret');
        $this->useCase     = new SubscribeUseCase($this->repository, $this->mailer, $this->logger, $this->usersConfig);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('findByEmail')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load subscriber.');

        $this->useCase->execute(new SubscribeRequest('new@example.com', 'Jan', SubscriberSource::Guest));
    }

    public function testNewEmailCreatesAndSavesSubscriber(): void
    {
        $this->repository
            ->method('findByEmail')
            ->willThrowException(new NewsletterSubscriberNotFoundException('new@example.com'));

        $this->repository->expects($this->once())->method('save');

        $response = $this->useCase->execute(new SubscribeRequest('new@example.com', 'Jan', SubscriberSource::Guest));

        $this->assertSame('new@example.com', $response->subscriber->email);
    }

    public function testExistingEmailReturnsExistingSubscriberWithoutSaving(): void
    {
        $existing = NewsletterSubscriber::create(
            NewsletterSubscriberId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'),
            'existing@example.com',
            'Eva',
            SubscriberSource::Guest,
        );

        $this->repository->method('findByEmail')->willReturn($existing);
        $this->repository->expects($this->never())->method('save');
        $this->mailer->expects($this->never())->method('sendSubscriptionConfirmedEmail');

        $response = $this->useCase->execute(new SubscribeRequest('existing@example.com', null, SubscriberSource::Guest));

        $this->assertSame($existing, $response->subscriber);
    }

    public function testGuestSourceStoredCorrectly(): void
    {
        $this->repository
            ->method('findByEmail')
            ->willThrowException(new NewsletterSubscriberNotFoundException('guest@example.com'));

        $this->repository->method('save');

        $response = $this->useCase->execute(new SubscribeRequest('guest@example.com', null, SubscriberSource::Guest));

        $this->assertSame(SubscriberSource::Guest, $response->subscriber->source);
    }

    public function testRegisteredSourceStoredCorrectly(): void
    {
        $this->repository
            ->method('findByEmail')
            ->willThrowException(new NewsletterSubscriberNotFoundException('reg@example.com'));

        $this->repository->method('save');

        $response = $this->useCase->execute(new SubscribeRequest('reg@example.com', null, SubscriberSource::Registered));

        $this->assertSame(SubscriberSource::Registered, $response->subscriber->source);
    }

    public function testNewSubscriberIsSentSubscriptionConfirmedEmail(): void
    {
        $this->repository
            ->method('findByEmail')
            ->willThrowException(new NewsletterSubscriberNotFoundException('new@example.com'));

        $expectedToken = UnsubscribeToken::generate('new@example.com', $this->usersConfig->cancellationSecret);
        $this->mailer->expects($this->once())
            ->method('sendSubscriptionConfirmedEmail')
            ->with('new@example.com', 'Jan', $expectedToken);

        $this->useCase->execute(new SubscribeRequest('new@example.com', 'Jan', SubscriberSource::Guest));
    }

    public function testSubscriptionConfirmedEmailFailureIsLoggedAndSwallowed(): void
    {
        $this->repository
            ->method('findByEmail')
            ->willThrowException(new NewsletterSubscriberNotFoundException('new@example.com'));

        $this->mailer->method('sendSubscriptionConfirmedEmail')
            ->willThrowException(new \RuntimeException('SMTP error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to send'));

        $response = $this->useCase->execute(new SubscribeRequest('new@example.com', 'Jan', SubscriberSource::Guest));

        $this->assertSame('new@example.com', $response->subscriber->email);
    }
}
