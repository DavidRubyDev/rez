<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\CreateSession;

use Rez\Domain\Session\Session;

final class CreateSessionResponse
{
    public function __construct(
        public readonly Session $session,
    ) {
    }
}
