<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Register;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Service\JwtService;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeRequest;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCaseInterface;
use Rez\Domain\Exception\EmailAlreadyRegisteredException;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\Newsletter\SubscriberSource;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;

final class RegisterUseCase implements RegisterUseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly SubscribeUseCaseInterface $subscribeUseCase,
        private readonly JwtService $jwtService,
    ) {
    }

    /**
     * @throws EmailAlreadyRegisteredException
     * @throws DatabaseException
     */
    public function execute(RegisterRequest $request): RegisterResponse
    {
        try {
            $this->userRepository->findByEmail($request->email);
            throw new EmailAlreadyRegisteredException($request->email);
        } catch (UserNotFoundException) {
            // Email is available.
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to check for existing user.', 0, $e);
        }

        $user = User::create(
            UserId::generate(),
            $request->name,
            $request->email,
            HashedPassword::fromPlainText($request->password),
            newsletterOptIn: $request->newsletterOptIn,
        );

        try {
            $this->userRepository->save($user);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save user.', 0, $e);
        }

        if ($request->newsletterOptIn) {
            $this->subscribeUseCase->execute(new SubscribeRequest($request->email, $request->name, SubscriberSource::Registered));
        }

        $token = $this->jwtService->generate($user->id, $user->role);

        return new RegisterResponse($user, $token);
    }
}
