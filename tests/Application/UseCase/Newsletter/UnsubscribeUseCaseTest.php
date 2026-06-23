<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Newsletter;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Application\UseCase\Newsletter\Unsubscribe\UnsubscribeRequest;
use Rez\Application\UseCase\Newsletter\Unsubscribe\UnsubscribeUseCase;
use Rez\Domain\Exception\NewsletterSubscriberNotFoundException;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Domain\Newsletter\SubscriberSource;

class UnsubscribeUseCaseTest extends TestCase
{
    private NewsletterRepositoryInterface&MockObject $repository;
    private UnsubscribeUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(NewsletterRepositoryInterface::class);
        $this->useCase    = new UnsubscribeUseCase($this->repository);
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
}
