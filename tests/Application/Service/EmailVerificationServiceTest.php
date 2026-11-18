<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Service;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailVerificationRepositoryInterface;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Service\EmailVerificationService;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeRequest;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeResponse;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCaseInterface;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Domain\Newsletter\SubscriberSource;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;
use Rez\Domain\User\UserRole;

class EmailVerificationServiceTest extends TestCase
{
    private EmailVerificationRepositoryInterface&MockObject $verificationRepository;
    private UserRepositoryInterface&MockObject $userRepository;
    private MailerInterface&MockObject $mailer;
    private SubscribeUseCaseInterface&MockObject $subscribeUseCase;
    private LoggerInterface&MockObject $logger;
    private EmailVerificationService $service;
    private User $user;

    protected function setUp(): void
    {
        $this->verificationRepository = $this->createMock(EmailVerificationRepositoryInterface::class);
        $this->userRepository         = $this->createMock(UserRepositoryInterface::class);
        $this->mailer                 = $this->createMock(MailerInterface::class);
        $this->subscribeUseCase       = $this->createMock(SubscribeUseCaseInterface::class);
        $this->logger                 = $this->createMock(LoggerInterface::class);
        $tokenGenerator               = $this->createMock(TokenGeneratorInterface::class);
        $tokenGenerator->method('generate')->willReturn('raw-verification-token');

        $this->service = new EmailVerificationService(
            $this->verificationRepository,
            $this->userRepository,
            $tokenGenerator,
            $this->mailer,
            $this->subscribeUseCase,
            new UsersConfig('super-secret-jwt-at-least-32-bytes-long', 'super-secret-cancellation-key', emailVerificationTtlMinutes: 90),
            $this->logger,
        );

        $this->user = $this->makeUser();
    }

    private function makeUser(bool $newsletterOptIn = false): User
    {
        return User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('x'), UserRole::Customer, $newsletterOptIn);
    }

    public function testSendLinkStoresOnlyTheTokensHashWithItsExpiry(): void
    {
        $this->verificationRepository->expects($this->once())
            ->method('save')
            ->with(
                $this->user->id,
                hash('sha256', 'raw-verification-token'),
                $this->callback(fn (DateTimeImmutable $issuedAt) => abs($issuedAt->getTimestamp() - time()) <= 2),
                $this->callback(fn (DateTimeImmutable $expiresAt) => abs($expiresAt->getTimestamp() - (time() + 90 * 60)) <= 2),
            );

        $this->service->sendLink($this->user, 'https://studio.example/verify');
    }

    public function testSendLinkEmailsTheLinkWithTheRawToken(): void
    {
        $this->mailer->expects($this->once())
            ->method('sendEmailVerification')
            ->with('john@example.com', 'John Doe', 'https://studio.example/verify?token=raw-verification-token');

        $this->service->sendLink($this->user, 'https://studio.example/verify/');
    }

    public function testSendLinkDoesNotEmailWhenTheTokenCannotBeSaved(): void
    {
        $this->verificationRepository->method('save')->willThrowException(new DatabaseException('pdo error'));
        $this->mailer->expects($this->never())->method('sendEmailVerification');

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to save email verification token.');

        $this->service->sendLink($this->user, 'https://studio.example/verify');
    }

    public function testSendLinkLogsAndSwallowsAMailerFailure(): void
    {
        $this->mailer->method('sendEmailVerification')->willThrowException(new \RuntimeException('smtp down'));
        $this->logger->expects($this->once())->method('error');

        $this->service->sendLink($this->user, 'https://studio.example/verify');
    }

    public function testMarkVerifiedSavesTheUserAsVerifiedAndDeletesTheLink(): void
    {
        $this->userRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (User $user) => $user->id->equals($this->user->id) && $user->isEmailVerified()));
        $this->verificationRepository->expects($this->once())->method('deleteForUser')->with($this->user->id);

        $verified = $this->service->markVerified($this->user);

        $this->assertTrue($verified->isEmailVerified());
        $this->assertEqualsWithDelta(time(), $verified->emailVerifiedAt?->getTimestamp(), 2);
    }

    public function testMarkVerifiedSubscribesAUserWhoOptedIn(): void
    {
        $this->subscribeUseCase->expects($this->once())
            ->method('execute')
            ->with($this->callback(
                fn (SubscribeRequest $request) => $request->email === 'john@example.com'
                    && $request->name === 'John Doe'
                    && $request->source === SubscriberSource::Registered
            ))
            ->willReturn(new SubscribeResponse(NewsletterSubscriber::create(
                NewsletterSubscriberId::generate(),
                'john@example.com',
                'John Doe',
                SubscriberSource::Registered,
            )));

        $this->service->markVerified($this->makeUser(newsletterOptIn: true));
    }

    public function testMarkVerifiedNeverSubscribesAUserWhoDidNotOptIn(): void
    {
        $this->subscribeUseCase->expects($this->never())->method('execute');

        $this->service->markVerified($this->user);
    }

    public function testMarkVerifiedLeavesAnAlreadyVerifiedUserAlone(): void
    {
        $verifiedAt = new DateTimeImmutable('2026-01-01 10:00:00');
        $user       = $this->makeUser(newsletterOptIn: true)->withEmailVerifiedAt($verifiedAt);
        $this->userRepository->expects($this->never())->method('save');
        $this->subscribeUseCase->expects($this->never())->method('execute');

        $this->assertSame($verifiedAt, $this->service->markVerified($user)->emailVerifiedAt);
    }

    public function testMarkVerifiedWrapsASaveFailure(): void
    {
        $this->userRepository->method('save')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to save user.');

        $this->service->markVerified($this->user);
    }
}
