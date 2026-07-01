<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Newsletter;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Application\UseCase\Newsletter\Broadcast\BroadcastRequest;
use Rez\Application\UseCase\Newsletter\Broadcast\BroadcastUseCase;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Domain\Newsletter\SubscriberSource;

class BroadcastUseCaseTest extends TestCase
{
    private NewsletterRepositoryInterface&MockObject $repository;
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;
    private BroadcastUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(NewsletterRepositoryInterface::class);
        $this->mailer     = $this->createMock(MailerInterface::class);
        $this->logger     = $this->createMock(LoggerInterface::class);
        $this->useCase    = new BroadcastUseCase($this->repository, $this->mailer, $this->logger);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('findAll')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load newsletter subscribers.');

        $this->useCase->execute(new BroadcastRequest('Yoga', new DateTimeImmutable('2026-07-01 10:00:00')));
    }

    public function testEmptySubscriberListSendsNoEmailsAndReturnsZero(): void
    {
        $this->repository->method('findAll')->willReturn([]);
        $this->mailer->expects($this->never())->method('sendNewClassNotification');

        $response = $this->useCase->execute(new BroadcastRequest('Yoga', new DateTimeImmutable('2026-07-01 10:00:00')));

        $this->assertSame(0, $response->sent);
    }

    public function testThreeSubscribersSendNotificationThreeTimes(): void
    {
        $this->repository->method('findAll')->willReturn([
            $this->makeSubscriber('a@example.com'),
            $this->makeSubscriber('b@example.com'),
            $this->makeSubscriber('c@example.com'),
        ]);

        $this->mailer->expects($this->exactly(3))->method('sendNewClassNotification');

        $this->useCase->execute(new BroadcastRequest('Yoga', new DateTimeImmutable('2026-07-01 10:00:00')));
    }

    public function testReturnsCorrectSentCount(): void
    {
        $this->repository->method('findAll')->willReturn([
            $this->makeSubscriber('a@example.com'),
            $this->makeSubscriber('b@example.com'),
        ]);

        $response = $this->useCase->execute(new BroadcastRequest('Pilates', new DateTimeImmutable('2026-07-02 09:00:00')));

        $this->assertSame(2, $response->sent);
    }

    public function testPerRecipientFailureIsLoggedAndSkipped(): void
    {
        $subscriber = $this->makeSubscriber('a@example.com');
        $this->repository->method('findAll')->willReturn([$subscriber]);

        $this->mailer
            ->method('sendNewClassNotification')
            ->willThrowException(new \RuntimeException('SMTP error'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('subscriber'),
                $this->arrayHasKey('subscriberId'),
            );

        $useCase = new BroadcastUseCase($this->repository, $this->mailer, $logger);

        $response = $useCase->execute(new BroadcastRequest('Yoga', new DateTimeImmutable('2026-07-01 10:00:00')));

        $this->assertSame(0, $response->sent);
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
