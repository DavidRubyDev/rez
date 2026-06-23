<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Newsletter;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeRequest;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCase;
use Rez\Domain\Exception\NewsletterSubscriberNotFoundException;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Domain\Newsletter\SubscriberSource;

class SubscribeUseCaseTest extends TestCase
{
    private NewsletterRepositoryInterface&MockObject $repository;
    private SubscribeUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(NewsletterRepositoryInterface::class);
        $this->useCase    = new SubscribeUseCase($this->repository);
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
}
