<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\AdminCreateUser;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\UseCase\Auth\RequestPasswordReset\RequestPasswordResetRequest;
use Rez\Application\UseCase\Auth\RequestPasswordReset\RequestPasswordResetUseCaseInterface;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeRequest;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCaseInterface;
use Rez\Domain\Exception\EmailAlreadyRegisteredException;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\Newsletter\SubscriberSource;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;

final class AdminCreateUserUseCase implements AdminCreateUserUseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TokenGeneratorInterface $tokenGenerator,
        private readonly SubscribeUseCaseInterface $subscribeUseCase,
        private readonly RequestPasswordResetUseCaseInterface $requestPasswordResetUseCase,
    ) {
    }

    /**
     * @throws EmailAlreadyRegisteredException
     * @throws DatabaseException
     */
    public function execute(AdminCreateUserRequest $request): AdminCreateUserResponse
    {
        try {
            $this->userRepository->findByEmail($request->email);
            throw new EmailAlreadyRegisteredException($request->email);
        } catch (UserNotFoundException) {
            // Email is available.
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to check for existing user.', 0, $e);
        }

        // Nobody is ever told this password — the admin flow always forces a reset via
        // emailed link before the account can be used, so it only needs to satisfy the
        // NOT NULL constraint on password_hash.
        $temporaryPassword = HashedPassword::fromPlainText($this->tokenGenerator->generate());

        $user = User::create(
            UserId::generate(),
            $request->name,
            $request->email,
            $temporaryPassword,
            $request->role,
            $request->newsletterOptIn,
        );

        try {
            $this->userRepository->save($user);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save user.', 0, $e);
        }

        if ($request->newsletterOptIn) {
            $this->subscribeUseCase->execute(new SubscribeRequest($request->email, $request->name, SubscriberSource::Admin));
        }

        $this->requestPasswordResetUseCase->execute(new RequestPasswordResetRequest($request->email, $request->resetBaseUrl));

        return new AdminCreateUserResponse($user);
    }
}
