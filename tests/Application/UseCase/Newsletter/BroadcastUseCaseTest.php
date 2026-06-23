<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Newsletter;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
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
    private BroadcastUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(NewsletterRepositoryInterface::class);
        $this->mailer     = $this->createMock(MailerInterface::class);
        $this->useCase    = new BroadcastUseCase($this->repository, $this->mailer);
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
