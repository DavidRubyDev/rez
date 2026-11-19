<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Register;

use Rez\Domain\User\User;

/**
 * No tokens: the new user can't sign in until they've followed the emailed link
 * (VerifyEmailUseCase signs them in then).
 */
final class RegisterResponse
{
    public function __construct(
        public readonly User $user,
    ) {
    }
}
