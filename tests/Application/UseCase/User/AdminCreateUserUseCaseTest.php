<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\User;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\UseCase\Auth\RequestPasswordReset\RequestPasswordResetRequest;
use Rez\Application\UseCase\Auth\RequestPasswordReset\RequestPasswordResetResponse;
use Rez\Application\UseCase\Auth\RequestPasswordReset\RequestPasswordResetUseCaseInterface;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeRequest;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeResponse;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCaseInterface;
use Rez\Application\UseCase\User\AdminCreateUser\AdminCreateUserRequest;
use Rez\Application\UseCase\User\AdminCreateUser\AdminCreateUserUseCase;
use Rez\Domain\Exception\EmailAlreadyRegisteredException;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Domain\Newsletter\SubscriberSource;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;
use Rez\Domain\User\UserRole;

class AdminCreateUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private TokenGeneratorInterface&MockObject $tokenGenerator;
    private SubscribeUseCaseInterface&MockObject $subscribeUseCase;
    private RequestPasswordResetUseCaseInterface&MockObject $requestPasswordResetUseCase;
    private AdminCreateUserUseCase $useCase;

    protected function setUp(): void
    {
        $this->userRepository              = $this->createMock(UserRepositoryInterface::class);
        $this->tokenGenerator               = $this->createMock(TokenGeneratorInterface::class);
        $this->subscribeUseCase             = $this->createMock(SubscribeUseCaseInterface::class);
        $this->requestPasswordResetUseCase  = $this->createMock(RequestPasswordResetUseCaseInterface::class);

        $this->userRepository->method('findByEmail')->willThrowException(new UserNotFoundException('john@example.com'));
        $this->tokenGenerator->method('generate')->willReturn('random-generated-password');
        $this->requestPasswordResetUseCase->method('execute')->willReturn(new RequestPasswordResetResponse(true));

        $this->useCase = new AdminCreateUserUseCase(
            $this->userRepository,
            $this->tokenGenerator,
            $this->subscribeUseCase,
            $this->requestPasswordResetUseCase,
        );
    }

    private function request(UserRole $role = UserRole::Customer, bool $newsletterOptIn = false): AdminCreateUserRequest
    {
        return new AdminCreateUserRequest('John Doe', 'john@example.com', 'https://admin.test/reset', $role, $newsletterOptIn);
    }

    public function testDuplicateEmailThrowsEmailAlreadyRegisteredException(): void
    {
        $existing = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('x'));
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->userRepository->method('findByEmail')->willReturn($existing);
        $useCase = new AdminCreateUserUseCase($this->userRepository, $this->tokenGenerator, $this->subscribeUseCase, $this->requestPasswordResetUseCase);

        $this->expectException(EmailAlreadyRegisteredException::class);

        $useCase->execute($this->request());
    }

    public function testFindByEmailDatabaseExceptionPropagates(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->userRepository->method('findByEmail')->willThrowException(new DatabaseException('pdo error'));
        $useCase = new AdminCreateUserUseCase($this->userRepository, $this->tokenGenerator, $this->subscribeUseCase, $this->requestPasswordResetUseCase);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to check for existing user.');

        $useCase->execute($this->request());
    }

    public function testSuccessSaveCalledExactlyOnce(): void
    {
        $this->userRepository->expects($this->once())->method('save');

        $this->useCase->execute($this->request());
    }

    public function testSavedUserPasswordMatchesGeneratedToken(): void
    {
        $this->userRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (User $u) => $u->password->verify('random-generated-password')));

        $this->useCase->execute($this->request());
    }

    public function testDefaultRoleIsCustomer(): void
    {
        $response = $this->useCase->execute($this->request());

        $this->assertSame(UserRole::Customer, $response->user->role);
    }

    public function testRoleCanBeSetToAdmin(): void
    {
        $response = $this->useCase->execute($this->request(role: UserRole::Admin));

        $this->assertSame(UserRole::Admin, $response->user->role);
    }

    public function testNewsletterOptInTrueSubscribesWithAdminSource(): void
    {
        $this->subscribeUseCase->expects($this->once())
            ->method('execute')
            ->with($this->callback(
                fn (SubscribeRequest $r) => $r->email === 'john@example.com'
                    && $r->name === 'John Doe'
                    && $r->source === SubscriberSource::Admin
            ))
            ->willReturn(new SubscribeResponse(NewsletterSubscriber::create(
                NewsletterSubscriberId::generate(),
                'john@example.com',
                'John Doe',
                SubscriberSource::Admin,
            )));

        $this->useCase->execute($this->request(newsletterOptIn: true));
    }

    public function testNewsletterOptInFalseNeverSubscribes(): void
    {
        $this->subscribeUseCase->expects($this->never())->method('execute');

        $this->useCase->execute($this->request(newsletterOptIn: false));
    }

    public function testTriggersPasswordResetEmailWithCorrectEmailAndResetBaseUrl(): void
    {
        $this->requestPasswordResetUseCase->expects($this->once())
            ->method('execute')
            ->with($this->callback(
                fn (RequestPasswordResetRequest $r) => $r->email === 'john@example.com'
                    && $r->resetBaseUrl === 'https://admin.test/reset'
            ))
            ->willReturn(new RequestPasswordResetResponse(true));

        $this->useCase->execute($this->request());
    }

    public function testReturnedUserHasCorrectNameAndEmail(): void
    {
        $response = $this->useCase->execute($this->request());

        $this->assertSame('John Doe', $response->user->name);
        $this->assertSame('john@example.com', $response->user->email);
    }
}
