<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\RefreshTokenRepositoryInterface;
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Service\AuthTokenIssuer;
use Rez\Application\Service\JwtService;
use Rez\Application\UseCase\Auth\Register\RegisterRequest;
use Rez\Application\UseCase\Auth\Register\RegisterUseCase;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeRequest;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCaseInterface;
use Rez\Domain\Exception\EmailAlreadyRegisteredException;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Domain\Newsletter\SubscriberSource;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeResponse;
use Rez\Application\Config\UsersConfig;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;
use Rez\Domain\User\HashedPassword;

class RegisterUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private SubscribeUseCaseInterface&MockObject $subscribeUseCase;
    private RefreshTokenRepositoryInterface&MockObject $refreshTokenRepository;
    private AuthTokenIssuer $issuer;
    private RegisterUseCase $useCase;

    protected function setUp(): void
    {
        $this->userRepository    = $this->createMock(UserRepositoryInterface::class);
        $this->subscribeUseCase  = $this->createMock(SubscribeUseCaseInterface::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $config                       = new UsersConfig('super-secret-jwt-at-least-32-bytes-long', 'super-secret-cancellation-key');
        $tokenGenerator               = $this->createMock(TokenGeneratorInterface::class);
        $tokenGenerator->method('generate')->willReturn('raw-refresh-token');
        $this->issuer = new AuthTokenIssuer(new JwtService($config), $this->refreshTokenRepository, $tokenGenerator, $config);

        $this->userRepository->method('findByEmail')->willThrowException(new UserNotFoundException('john@example.com'));

        $this->useCase = new RegisterUseCase($this->userRepository, $this->subscribeUseCase, $this->issuer);
    }

    private function request(bool $newsletterOptIn = false): RegisterRequest
    {
        return new RegisterRequest('John Doe', 'john@example.com', 'correct-horse-battery-staple', $newsletterOptIn);
    }

    public function testDuplicateEmailThrowsEmailAlreadyRegisteredException(): void
    {
        $existing = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('x'));
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->userRepository->method('findByEmail')->willReturn($existing);
        $useCase = new RegisterUseCase($this->userRepository, $this->subscribeUseCase, $this->issuer);

        $this->expectException(EmailAlreadyRegisteredException::class);

        $useCase->execute($this->request());
    }

    public function testFindByEmailDatabaseExceptionPropagates(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->userRepository->method('findByEmail')->willThrowException(new DatabaseException('pdo error'));
        $useCase = new RegisterUseCase($this->userRepository, $this->subscribeUseCase, $this->issuer);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to check for existing user.');

        $useCase->execute($this->request());
    }

    public function testSuccessSaveCalledExactlyOnce(): void
    {
        $this->userRepository->expects($this->once())->method('save');

        $this->useCase->execute($this->request());
    }

    public function testSuccessReturnsAccessAndRefreshTokens(): void
    {
        $this->refreshTokenRepository->expects($this->once())->method('save');

        $response = $this->useCase->execute($this->request());

        $this->assertNotSame('', $response->tokens->accessToken);
        $this->assertSame('raw-refresh-token', $response->tokens->refreshToken);
    }

    public function testRefreshTokenIsIssuedOnlyAfterTheUserIsSaved(): void
    {
        $this->userRepository->method('save')->willThrowException(new DatabaseException('pdo error'));
        $this->refreshTokenRepository->expects($this->never())->method('save');

        $this->expectException(DatabaseException::class);

        $this->useCase->execute($this->request());
    }

    public function testSuccessWithNewsletterOptInTrueCallsSubscribeUseCase(): void
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

        $this->useCase->execute($this->request(newsletterOptIn: true));
    }

    public function testSuccessWithNewsletterOptInFalseNeverCallsSubscribeUseCase(): void
    {
        $this->subscribeUseCase->expects($this->never())->method('execute');

        $this->useCase->execute($this->request(newsletterOptIn: false));
    }

    public function testReturnedUserHasCorrectNameAndEmail(): void
    {
        $response = $this->useCase->execute($this->request());

        $this->assertSame('John Doe', $response->user->name);
        $this->assertSame('john@example.com', $response->user->email);
    }
}
