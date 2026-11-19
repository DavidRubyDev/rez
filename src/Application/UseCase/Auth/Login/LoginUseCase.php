<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Login;

use DateTimeImmutable;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\LoginFailureRepositoryInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Service\AuthTokenIssuer;
use Rez\Domain\Auth\LoginThrottleKey;
use Rez\Domain\Exception\EmailNotVerifiedException;
use Rez\Domain\Exception\InvalidCredentialsException;
use Rez\Domain\Exception\TooManyLoginAttemptsException;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;

final class LoginUseCase implements LoginUseCaseInterface
{
    /**
     * Checked against when the email is unknown, so that answer takes as long as a wrong
     * password does. Same algorithm and cost as HashedPassword::fromPlainText() produces; the
     * password behind it was random and discarded.
     */
    private const TIMING_DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$aW5hWEc4UDJRLnVPcHpHZw$jWMVvXrM7XCHEORp+8nssTTgW9N52pdLrikCgImlifs';

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly AuthTokenIssuer $tokenIssuer,
        private readonly LoginFailureRepositoryInterface $loginFailureRepository,
        private readonly UsersConfig $config,
    ) {
    }

    /**
     * @throws TooManyLoginAttemptsException
     * @throws InvalidCredentialsException
     * @throws EmailNotVerifiedException
     * @throws DatabaseException
     */
    public function execute(LoginRequest $request): LoginResponse
    {
        $key = LoginThrottleKey::for($request->email, $request->clientKey);
        $now = Utc::now();

        // Refused before the password is even looked at — otherwise a locked-out attacker
        // could keep guessing and learn the right password from the one answer that differs.
        $this->assertNotThrottled($key, $now);

        $user            = $this->findUser($request->email);
        $passwordToCheck = $user->password ?? HashedPassword::fromHash(self::TIMING_DUMMY_HASH);

        if (!$passwordToCheck->verify($request->password) || $user === null) {
            $this->recordFailure($key, $now);
            throw new InvalidCredentialsException();
        }

        $this->clearFailures($key);

        // Only said once the password is right, so it reveals nothing to someone guessing.
        if (!$user->isEmailVerified()) {
            throw new EmailNotVerifiedException();
        }

        if ($user->password->needsRehash()) {
            $user = $this->saveRehashed($user, $request->password);
        }

        return new LoginResponse($user, $this->tokenIssuer->issue($user));
    }

    /**
     * @throws TooManyLoginAttemptsException
     * @throws DatabaseException
     */
    private function assertNotThrottled(LoginThrottleKey $key, DateTimeImmutable $now): void
    {
        $windowStart = $now->modify("-{$this->config->loginFailureWindowSeconds} seconds");

        try {
            $failures = $this->loginFailureRepository->failedSince($key, $windowStart);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load login failures.', 0, $e);
        }

        if (count($failures) < $this->config->loginMaxFailedAttempts) {
            return;
        }

        // A sliding window: one more attempt opens up as soon as the oldest failure in it ages out.
        $oldestLeavesWindowAt = $failures[0]->getTimestamp() + $this->config->loginFailureWindowSeconds;

        throw new TooManyLoginAttemptsException(max(1, $oldestLeavesWindowAt - $now->getTimestamp()));
    }

    /**
     * @throws DatabaseException
     */
    private function findUser(string $email): ?User
    {
        try {
            return $this->userRepository->findByEmail($email);
        } catch (UserNotFoundException) {
            return null;
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load user.', 0, $e);
        }
    }

    /**
     * @throws DatabaseException
     */
    private function saveRehashed(User $user, string $plainText): User
    {
        $rehashed = $user->withPassword(HashedPassword::fromPlainText($plainText));

        try {
            $this->userRepository->save($rehashed);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save rehashed password.', 0, $e);
        }

        return $rehashed;
    }

    /**
     * @throws DatabaseException
     */
    private function recordFailure(LoginThrottleKey $key, DateTimeImmutable $at): void
    {
        try {
            $this->loginFailureRepository->record($key, $at);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to record login failure.', 0, $e);
        }
    }

    /**
     * @throws DatabaseException
     */
    private function clearFailures(LoginThrottleKey $key): void
    {
        try {
            $this->loginFailureRepository->clear($key);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to clear login failures.', 0, $e);
        }
    }
}
