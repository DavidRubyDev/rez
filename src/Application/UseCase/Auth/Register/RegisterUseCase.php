<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Register;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Service\EmailVerificationService;
use Rez\Application\Service\PasswordPolicy;
use Rez\Domain\Exception\EmailAlreadyRegisteredException;
use Rez\Domain\Exception\PasswordTooLongException;
use Rez\Domain\Exception\PasswordTooShortException;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;

final class RegisterUseCase implements RegisterUseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly EmailVerificationService $emailVerification,
    ) {
    }

    /**
     * @throws PasswordTooShortException
     * @throws PasswordTooLongException
     * @throws EmailAlreadyRegisteredException
     * @throws DatabaseException
     */
    public function execute(RegisterRequest $request): RegisterResponse
    {
        $this->passwordPolicy->assertAllows($request->password);

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

        $this->emailVerification->sendLink($user, $request->verificationBaseUrl);

        return new RegisterResponse($user);
    }
}
