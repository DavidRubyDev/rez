<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Login;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Service\JwtService;
use Rez\Domain\Exception\InvalidCredentialsException;
use Rez\Domain\Exception\UserNotFoundException;

final class LoginUseCase implements LoginUseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly JwtService $jwtService,
    ) {
    }

    /**
     * @throws InvalidCredentialsException
     * @throws DatabaseException
     */
    public function execute(LoginRequest $request): LoginResponse
    {
        try {
            $user = $this->userRepository->findByEmail($request->email);
        } catch (UserNotFoundException) {
            throw new InvalidCredentialsException();
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load user.', 0, $e);
        }

        if (!$user->password->verify($request->password)) {
            throw new InvalidCredentialsException();
        }

        $token = $this->jwtService->generate($user->id, $user->role);

        return new LoginResponse($user, $token);
    }
}
